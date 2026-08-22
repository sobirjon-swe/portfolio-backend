<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PageText;
use App\Repositories\Contracts\PageTextRepositoryInterface;
use Illuminate\Support\Collection;

class PageTextRepository implements PageTextRepositoryInterface
{
    public function all(): Collection
    {
        return PageText::query()->get()->keyBy('key');
    }

    public function put(string $key, array $translations): PageText
    {
        $text = PageText::query()->firstOrNew(['key' => $key]);

        // setTranslations replaces the whole map, so a locale the admin
        // cleared disappears rather than lingering at its old wording.
        $text->setTranslations('value', $translations);
        $text->save();

        return $text;
    }

    public function forget(string $key): bool
    {
        return PageText::query()->where('key', $key)->delete() > 0;
    }
}
