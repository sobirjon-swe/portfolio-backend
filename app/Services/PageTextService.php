<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PageText;
use App\Repositories\Contracts\PageTextRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Serves and stores the copy overrides the admin has made.
 *
 * The public read is on every page load of the site, so it is cached and the
 * cache is dropped on write. The payload is small — only keys someone has
 * actually edited — and empty on a site nobody has customised yet.
 */
class PageTextService
{
    private const CACHE_KEY = 'page-texts.public';

    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly PageTextRepositoryInterface $repository,
    ) {}

    /**
     * Overrides for one locale, as a flat key => text map.
     *
     * A key with no text for the requested locale is left out entirely rather
     * than returned empty, so the frontend keeps the bundled wording instead
     * of rendering a blank where a sentence used to be.
     *
     * @return array<string, string>
     */
    public function forLocale(string $locale): array
    {
        $all = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->repository->all()
                ->map(fn (PageText $t): array => $t->getTranslations('value'))
                ->all(),
        );

        $out = [];

        foreach ($all as $key => $translations) {
            $value = trim((string) ($translations[$locale] ?? ''));

            if ($value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * Every override in every locale, for the admin editor.
     *
     * @return array<string, array<string, string>>
     */
    public function all(): array
    {
        return $this->repository->all()
            ->map(fn (PageText $t): array => $t->getTranslations('value'))
            ->all();
    }

    /**
     * Save one key. Clearing every locale removes the override entirely,
     * which is how the admin restores the text the app ships with.
     *
     * @param  array<string, string|null>  $translations
     */
    public function save(string $key, array $translations): void
    {
        if (! PageText::isEditable($key)) {
            throw new InvalidArgumentException("[{$key}] is not an editable text.");
        }

        $locales = (array) config('page-texts.locales', []);

        $clean = [];

        foreach ($locales as $locale) {
            $value = trim((string) ($translations[$locale] ?? ''));

            if ($value !== '') {
                $clean[$locale] = $value;
            }
        }

        if ($clean === []) {
            $this->repository->forget($key);
        } else {
            $this->repository->put($key, $clean);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The prefixes the admin editor is allowed to offer.
     *
     * @return array<int, string>
     */
    public function editablePrefixes(): array
    {
        return (array) config('page-texts.editable_prefixes', []);
    }
}
