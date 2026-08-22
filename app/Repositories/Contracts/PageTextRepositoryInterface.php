<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PageText;
use Illuminate\Support\Collection;

interface PageTextRepositoryInterface
{
    /**
     * Every override, keyed by translation key.
     *
     * @return Collection<string, PageText>
     */
    public function all(): Collection;

    /**
     * Store or replace one key's translations.
     *
     * @param  array<string, string>  $translations  Locale => text.
     */
    public function put(string $key, array $translations): PageText;

    /**
     * Drop an override so the key falls back to the bundled text.
     */
    public function forget(string $key): bool;
}
