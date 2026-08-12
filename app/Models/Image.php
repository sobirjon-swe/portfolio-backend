<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * An image attached to a project or a post.
 *
 * Exactly one of `path` (an upload on the configured disk) or `url` (an
 * externally hosted image) is set; `resolved_url` returns whichever it is, so
 * callers never have to care which kind they are looking at.
 */
#[Fillable(['path', 'url', 'alt', 'sort_order'])]
class Image extends Model
{
    /** @use HasFactory<ImageFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // Deleting the row must not leave the file behind. This fires for
        // cascade deletes too, because the owner deletes its images explicitly
        // (see HasImages) rather than relying on the database's FK cascade.
        static::deleting(function (self $image): void {
            $image->deleteFile();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * A browser-usable absolute URL, whether the image was uploaded or linked.
     *
     * @return Attribute<string|null, never>
     */
    protected function resolvedUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->url !== null && $this->url !== '') {
                return $this->url;
            }

            if ($this->path === null || $this->path === '') {
                return null;
            }

            return Storage::disk(config('images.disk'))->url($this->path);
        });
    }

    /**
     * Remove the backing file, if this image is an upload.
     */
    public function deleteFile(): void
    {
        if ($this->path === null || $this->path === '') {
            return;
        }

        Storage::disk(config('images.disk'))->delete($this->path);
    }
}
