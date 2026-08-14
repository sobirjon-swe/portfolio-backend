<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Input tidying the admin panel would otherwise have to do in the browser.
 *
 * Both of these were real dead ends when adding a record: typing a company
 * site as "acme.uz" failed the `url` rule, and clearing the optional sort
 * order sent null into a NOT NULL integer column. Neither is a mistake worth
 * a validation error — the intent is unambiguous in both cases.
 */
trait NormalizesInput
{
    /**
     * Give bare domains a scheme, so "acme.uz" passes the `url` rule.
     *
     * Existing schemes are left alone — including mailto: and tel:, which are
     * legitimate and must not be rewritten to https.
     *
     * Only inputs that actually look like a host get the prefix. Prepending it
     * unconditionally would turn any typo into a technically-valid URL —
     * "not-a-url" would sail through as https://not-a-url — which trades one
     * confusing rejection for a silently broken link.
     *
     * @param  list<string>  $keys
     */
    protected function normalizeUrls(array $keys): void
    {
        $patch = [];

        foreach ($keys as $key) {
            $value = $this->input($key);

            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);

            // Already has a scheme, or is empty — nothing to do.
            if ($trimmed === '' || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $trimmed) === 1) {
                continue;
            }

            // A dotted host, optionally with a port, before any path or query.
            $host = preg_split('#[/?\#]#', $trimmed, 2)[0];

            if (preg_match('#^[a-z0-9\-]+(\.[a-z0-9\-]+)+(:\d+)?$#i', $host) !== 1) {
                continue;
            }

            $patch[$key] = 'https://'.$trimmed;
        }

        if ($patch !== []) {
            $this->merge($patch);
        }
    }

    /**
     * Replace a present-but-blank numeric field with its column default.
     *
     * Only touches keys the client actually sent, so `sometimes` rules still
     * behave as intended for fields that were left out entirely.
     *
     * @param  array<string, int|float>  $defaults
     */
    protected function defaultNumbers(array $defaults): void
    {
        $patch = [];

        foreach ($defaults as $key => $default) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->input($key);

            if ($value === null || $value === '') {
                $patch[$key] = $default;
            }
        }

        if ($patch !== []) {
            $this->merge($patch);
        }
    }
}
