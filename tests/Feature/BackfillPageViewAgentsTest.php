<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PageView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The backfill rewrites rows the dashboard already reported on, so these cover
 * the ways it could do harm: inventing facts about rows it cannot classify,
 * and giving a different answer on a second run.
 */
class BackfillPageViewAgentsTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const GPTBOT = 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot';

    /** Write a row the way the pre-classifier code did: agent only. */
    private function unclassified(string $userAgent): PageView
    {
        $view = PageView::factory()->create(['user_agent' => $userAgent]);

        PageView::query()->whereKey($view->id)->update([
            'device' => null, 'browser' => null, 'platform' => null, 'is_bot' => false,
        ]);

        return $view->refresh();
    }

    public function test_it_classifies_rows_written_before_the_parser_existed(): void
    {
        $desktop = $this->unclassified(self::CHROME);
        $phone = $this->unclassified(self::IPHONE);

        $this->artisan('analytics:backfill')->assertSuccessful();

        $desktop->refresh();
        $this->assertSame('desktop', $desktop->device);
        $this->assertSame('Chrome', $desktop->browser);
        $this->assertSame('Windows', $desktop->platform);
        $this->assertFalse($desktop->is_bot);

        $phone->refresh();
        $this->assertSame('mobile', $phone->device);
        $this->assertSame('iOS', $phone->platform);
    }

    public function test_a_crawler_that_was_counted_as_a_person_is_corrected(): void
    {
        $bot = $this->unclassified(self::GPTBOT);

        $this->artisan('analytics:backfill')
            ->expectsOutputToContain('reclassified from visitor to crawler')
            ->assertSuccessful();

        $this->assertTrue($bot->refresh()->is_bot);
    }

    public function test_a_row_with_no_user_agent_is_left_alone(): void
    {
        $view = PageView::factory()->create(['user_agent' => null]);
        PageView::query()->whereKey($view->id)->update(['device' => null, 'is_bot' => false]);

        $this->artisan('analytics:backfill')
            ->expectsOutputToContain('had no User-Agent')
            ->assertSuccessful();

        $view->refresh();
        // Not flipped to bot: absence of a stored agent is not evidence of one.
        $this->assertFalse($view->is_bot);
        $this->assertNull($view->device);
    }

    public function test_a_second_run_changes_nothing(): void
    {
        $this->unclassified(self::CHROME);
        $this->unclassified(self::GPTBOT);

        $this->artisan('analytics:backfill')->assertSuccessful();

        $this->artisan('analytics:backfill')
            ->expectsOutputToContain('0 would change')
            ->assertSuccessful();
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $view = $this->unclassified(self::CHROME);

        $this->artisan('analytics:backfill', ['--dry-run' => true])
            ->expectsOutputToContain('Scanned')
            ->expectsOutputToContain('nothing was written')
            ->assertSuccessful();

        $this->assertNull($view->refresh()->device);
    }

    public function test_the_dashboard_reports_the_backfilled_breakdowns(): void
    {
        $this->unclassified(self::CHROME);
        $this->unclassified(self::IPHONE);
        $this->unclassified(self::GPTBOT);

        $this->artisan('analytics:backfill')->assertSuccessful();

        \Laravel\Sanctum\Sanctum::actingAs(\App\Models\User::factory()->create());

        $response = $this->getJson('/api/v1/page-views/stats')->assertOk();

        // Two people and one crawler, counted apart.
        $response->assertJsonPath('data.totals.views_all', 2);
        $response->assertJsonPath('data.totals.bot_views_window', 1);
        $this->assertCount(2, $response->json('data.devices'));
    }
}
