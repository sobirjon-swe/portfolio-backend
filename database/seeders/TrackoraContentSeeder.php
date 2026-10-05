<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/** Enrich only the known sample copy; never replace an editor's custom text. */
class TrackoraContentSeeder extends Seeder
{
    public function run(): void
    {
        $project = Project::query()->where('slug', 'trackora')->first();
        if (! $project) {
            return;
        }
        $original = 'A time and habit tracker built with Laravel and React, with offline-first sync.';
        $descriptions = [
            'en' => $original."\n\n## Purpose\nTrack time and habits.\n\n## Implementation\nBuilt with Laravel and React.\n\n## Capabilities\nOffline-first synchronization.",
            'uz' => "Laravel va React asosida qurilgan, offline-first sinxronlashga ega vaqt va odatlar kuzatuvchisi.\n\n## Maqsad\nVaqt va odatlarni kuzatish.\n\n## Yechim\nLaravel va React asosida qurilgan.\n\n## Imkoniyatlar\nOffline-first sinxronlash.",
            'ru' => "Приложение для отслеживания времени и привычек на Laravel и React с offline-first синхронизацией.\n\n## Цель\nОтслеживание времени и привычек.\n\n## Реализация\nСоздано на Laravel и React.\n\n## Возможности\nOffline-first синхронизация.",
        ];
        // Only this exact sample is evidence for the translations below.
        $current = $project->getTranslations('description');
        if (($current['en'] ?? null) !== $original && ($current['en'] ?? null) !== $descriptions['en']) {
            return;
        }
        foreach ($descriptions as $locale => $description) {
            if (empty($current[$locale]) || ($locale === 'en' && $current[$locale] === $original)) {
                $project->setTranslation('description', $locale, $description);
            }
            if (! $project->hasTranslation('title', $locale)) {
                $project->setTranslation('title', $locale, 'Trackora');
            }
        }
        $project->save();
    }
}
