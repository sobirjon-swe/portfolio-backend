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

    public function test_visitor_ip_is_stored_only_as_a_keyed_hash(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson('/api/v1/page-views', ['page' => '/'])
            ->assertCreated();

        $view = PageView::query()->sole();

        $this->assertNotNull($view->ip_hash);
        $this->assertSame(
            hash_hmac('sha256', '203.0.113.7', (string) config('app.key')),
            $view->ip_hash,
        );
        // The raw address must not survive anywhere on the record.
        $this->assertStringNotContainsString('203.0.113.7', json_encode($view->getAttributes()));
    }

    public function test_the_response_never_echoes_the_visitor_hash(): void
    {
        $this->postJson('/api/v1/page-views', ['page' => '/'])
            ->assertCreated()
            ->assertJsonMissingPath('data.ip_hash')
            ->assertJsonMissingPath('data.user_agent');
    }

    public function test_prune_deletes_views_past_the_retention_window(): void
    {
        config()->set('analytics.retention_days', 30);

        PageView::factory()->create(['created_at' => now()->subDays(31)]);
        PageView::factory()->create(['created_at' => now()->subDays(29)]);

        $this->artisan('page-views:prune')
            ->expectsOutputToContain('Pruned 1 page view(s)')
            ->assertSuccessful();

        $this->assertSame(1, PageView::query()->count());
    }

    public function test_prune_is_a_no_op_when_retention_is_disabled(): void
    {
        config()->set('analytics.retention_days', 0);

        PageView::factory()->create(['created_at' => now()->subYears(5)]);

        $this->artisan('page-views:prune')->assertSuccessful();

        $this->assertSame(1, PageView::query()->count());
    }

    public function test_prune_accepts_a_days_override(): void
    {
        config()->set('analytics.retention_days', 365);

        PageView::factory()->create(['created_at' => now()->subDays(10)]);

        $this->artisan('page-views:prune', ['--days' => 5])->assertSuccessful();

        $this->assertSame(0, PageView::query()->count());
    }
}
