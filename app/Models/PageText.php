<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * An override for one line of frontend copy.
 *
 * Rows exist only for keys someone has actually edited. Everything else falls
 * through to the text bundled with the app, which stays the default and the
 * fallback — so an empty table, or an unreachable API, renders the real site
 * rather than a page of blanks.
 */
#[Fillable(['key', 'value'])]
class PageText extends Model
{
    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['value'];

    /**
     * Whether a translation key may be overridden at all.
     *
     * Guards the write path: the admin UI only offers editable keys, but the
     * API must not take the UI's word for it.
     */
    public static function isEditable(string $key): bool
    {
        foreach ((array) config('page-texts.editable_prefixes', []) as $prefix) {
            if ($key === $prefix || str_starts_with($key, $prefix.'.')) {
                return true;
            }
        }

        return false;
    }
}
