<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Image;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model an ordered gallery of images.
 *
 * @phpstan-require-extends Model
 */
trait HasImages
{
    public static function bootHasImages(): void
    {
        // Delete images one by one rather than with a bulk query, so each row's
        // `deleting` hook runs and removes its file from disk. A database-level
        // cascade would orphan every uploaded file.
        static::deleting(function (self $model): void {
            $model->images->each->delete();
        });
    }

    /**
     * @return MorphMany<Image, $this>
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The first image of the gallery, kept under the name the API has always
     * used for the single cover URL.
     *
     * @return Attribute<string|null, never>
     */
    protected function coverImage(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->images->first()?->resolved_url);
    }
}
