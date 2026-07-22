<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PageViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_can_record_a_page_view_without_auth(): void
    {
        $this->postJson('/api/v1/page-views', ['page' => '/projects'])
            ->assertCreated()
            ->assertJsonPath('data.page', '/projects')
            ->assertJsonPath('message', 'Page view recorded.');

        $this->assertDatabaseHas('page_views', ['page' => '/projects']);
    }

    public function test_recording_requires_a_page(): void
    {
        $this->postJson('/api/v1/page-views', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('page');
    }

    public function test_stats_are_protected(): void
    {
        $this->getJson('/api/v1/page-views/stats')->assertUnauthorized();
    }

    public function test_stats_aggregate_views_per_page(): void
    {
        Sanctum::actingAs(User::factory()->create());

        PageView::factory()->count(3)->create(['page' => '/']);
        PageView::factory()->count(1)->create(['page' => '/blog']);

        $this->getJson('/api/v1/page-views/stats')
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.per_page.0.page', '/') // most viewed first
            ->assertJsonPath('data.per_page.0.views', 3);
    }
}
