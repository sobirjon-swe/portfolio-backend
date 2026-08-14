<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Slug generation for models whose title is translatable.
 *
 * Posts and news both need it and the rule has to stay identical between them:
 * a reader who learns that /blog/some-title works expects /news/some-title to
 * behave the same way.
 */
trait GeneratesUniqueSlug
{
    /**
     * A slug for `$title`, unique within `$model`'s table.
     *
     * `$ignoreId` excludes the row being updated, so re-saving a record without
     * renaming it does not append a suffix to its own slug.
     *
     * @param  class-string<Model>  $model
     * @param  array<string, string>|string  $title
     */
    private function uniqueSlugFor(string $model, array|string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($this->titleString($title));
        $slug = $base;
        $suffix = 2;

        while ($model::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Resolve a plain string from a translatable title (default locale first).
     *
     * The slug is one shared URL across all locales, so it is built from a
     * single language rather than whichever one the admin happened to be
     * editing in.
     *
     * @param  array<string, string>|string  $title
     */
    private function titleString(array|string $title): string
    {
        if (is_array($title)) {
            $values = array_filter($title, static fn ($v) => is_string($v) && $v !== '');

            return (string) ($title['en'] ?? (array_values($values)[0] ?? ''));
        }

        return $title;
    }
}
