<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The downloadable CV. One row per published language — see ResumeService.
 */
#[Fillable(['locale', 'path', 'original_name', 'size', 'version'])]
class Resume extends Model
{
    protected static function booted(): void
    {
        // Removing the row must not leave the PDF orphaned on disk.
        static::deleting(fn (self $resume) => $resume->deleteFile());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'version' => 'integer',
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk(config('documents.disk'))->url($this->path));
    }

    public function deleteFile(): void
    {
        if ($this->path !== '') {
            Storage::disk(config('documents.disk'))->delete($this->path);
        }
    }
}
