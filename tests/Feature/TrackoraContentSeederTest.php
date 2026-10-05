<?php

namespace Tests\Feature;

use App\Models\Project;
use Database\Seeders\TrackoraContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackoraContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_is_translated_and_repeated_runs_preserve_editor_changes(): void
    {
        $project = Project::factory()->create([
            'slug' => 'trackora',
            'title' => ['en' => 'Trackora'],
            'description' => ['en' => 'A time and habit tracker built with Laravel and React, with offline-first sync.'],
        ]);
        $this->seed(TrackoraContentSeeder::class);
        $project->refresh();
        $this->assertStringContainsString('## Maqsad', $project->getTranslation('description', 'uz'));
        $this->assertStringContainsString('## Цель', $project->getTranslation('description', 'ru'));
        $project->setTranslation('description', 'uz', 'Editor copy')->save();
        $this->seed(TrackoraContentSeeder::class);
        $this->assertSame('Editor copy', $project->fresh()->getTranslation('description', 'uz'));
    }

    public function test_unrelated_custom_description_is_not_changed(): void
    {
        $project = Project::factory()->create(['slug' => 'trackora', 'description' => ['en' => 'Custom case study']]);
        $this->seed(TrackoraContentSeeder::class);
        $this->assertSame(['en' => 'Custom case study'], $project->fresh()->getTranslations('description'));
    }
}
