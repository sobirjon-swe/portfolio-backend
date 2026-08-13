<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Turns a visitor's IP into a keyed digest.
 *
 * The whole IPv4 space is only ~4 billion addresses, so a plain hash is
 * reversible by brute force in minutes. Keying with APP_KEY means the digests
 * are worthless to anyone without the application secret.
 *
 * Shared by analytics, comments and likes so every one of them stores the same
 * digest for the same visitor — that is what lets likes deduplicate.
 */
class IpHasher
{
    public function hash(?string $ipAddress): ?string
    {
        if ($ipAddress === null || $ipAddress === '') {
            return null;
        }

        return hash_hmac('sha256', $ipAddress, (string) config('app.key'));
    }
}
