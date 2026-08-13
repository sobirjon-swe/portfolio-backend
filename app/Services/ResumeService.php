<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Resume;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class ResumeService
{
    /** Locales the CV may be published in. */
    public const LOCALES = ['en', 'uz', 'ru'];

    /**
     * The CV to offer a visitor reading in `$locale`.
     *
     * Falls back rather than returning nothing: a visitor reading in Uzbek
     * should still get a downloadable CV when only the English one exists.
     * Order: the requested language, then the app's fallback locale, then
     * whatever is published.
     */
    public function forLocale(?string $locale = null): ?Resume
    {
        $locale = $this->normalise($locale ?? app()->getLocale());

        $published = $this->all()->keyBy('locale');

        return $published->get($locale)
            ?? $published->get($this->normalise((string) config('app.fallback_locale')))
            ?? $published->first();
    }

    /**
     * Every published CV, for the admin's per-language slots.
     *
     * @return Collection<int, Resume>
     */
    public function all(): Collection
    {
        return Resume::query()->orderBy('locale')->get();
    }

    /**
     * Store a CV for one language, replacing that language's previous file.
     * The other languages are untouched.
     */
    public function replace(UploadedFile $file, string $locale): Resume
    {
        $locale = $this->normalise($locale);

        return DB::transaction(function () use ($file, $locale): Resume {
            $previous = Resume::query()->where('locale', $locale)->first();

            // Delete first: `locale` is unique, so the old row has to go before
            // the new one can take its place.
            $version = ($previous?->version ?? 0) + 1;
            $previous?->delete();

            return Resume::query()->create([
                'locale' => $locale,
                'path' => $file->store((string) config('documents.directory'), config('documents.disk')),
                // The client-supplied name is shown, never used to build a path.
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'version' => $version,
            ]);
        });
    }

    public function delete(string $locale): void
    {
        Resume::query()->where('locale', $this->normalise($locale))->first()?->delete();
    }

    private function normalise(string $locale): string
    {
        $locale = strtolower(trim($locale));

        return in_array($locale, self::LOCALES, true) ? $locale : 'en';
    }
}
