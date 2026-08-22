<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\PageView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The dashboard's whole purpose is answering "how many people came by", so
 * these cover the ways that number can quietly become wrong: crawlers counted
 * as people, one person counted many times, and days bucketed in the wrong
 * timezone.
 */
class PageViewStatsTest extends TestCase
{
    use RefreshDatabase;

    private function timezone(): string
    {
        return (string) config('analytics.display_timezone');
    }

    private function actAsAdmin(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_crawlers_are_excluded_from_every_visitor_figure(): void
    {
        $this->actAsAdmin();

        PageView::factory()->count(2)->create();
        PageView::factory()->count(7)->bot()->create();

        $response = $this->getJson('/api/v1/page-views/stats')->assertOk();

        $response->assertJsonPath('data.totals.views_all', 2);
        $response->assertJsonPath('data.total', 2);
        // The bots are not discarded, only kept out of the human figures.
        $response->assertJsonPath('data.totals.bot_views_window', 7);
    }

    public function test_repeat_visits_from_one_person_count_as_one_visitor(): void
    {
        $this->actAsAdmin();

        PageView::factory()->count(5)->fromVisitor('same-person')->create();
        PageView::factory()->count(1)->fromVisitor('someone-else')->create();

        $this->getJson('/api/v1/page-views/stats')
            ->assertOk()
            ->assertJsonPath('data.totals.views_all', 6)
            ->assertJsonPath('data.totals.visitors_all', 2);
    }

    public function test_today_is_bucketed_in_the_admins_timezone(): void
    {
        $this->actAsAdmin();

        // 01:00 local yesterday is still 20:00 UTC the day before. Bucketing
        // in UTC would file this under today and overstate the day's traffic.
        $lastNight = Carbon::now($this->timezone())->startOfDay()->subHours(2);

        PageView::factory()->at($lastNight->copy()->utc())->create();
        PageView::factory()->at(Carbon::now($this->timezone())->startOfDay()->addHour()->utc())->create();

        $this->getJson('/api/v1/page-views/stats')
            ->assertOk()
            ->assertJsonPath('data.totals.views_today', 1)
            ->assertJsonPath('data.totals.views_all', 2);
    }

    public function test_the_trend_covers_every_day_in_the_window_including_quiet_ones(): void
    {
        $this->actAsAdmin();

        PageView::factory()->at(Carbon::now($this->timezone())->startOfDay()->addHour()->utc())->create();

        $response = $this->getJson('/api/v1/page-views/stats')->assertOk();

        $days = (int) config('analytics.trend_days');
        $trend = $response->json('data.trend');

        $this->assertCount($days, $trend, 'The chart must have a bar for every day, not only busy ones.');
        $this->assertSame(
            Carbon::now($this->timezone())->format('Y-m-d'),
            $trend[$days - 1]['date'],
            'The window must end on today, oldest first.'
        );
        $this->assertSame(1, $trend[$days - 1]['views']);
        $this->assertSame(0, $trend[0]['views']);
    }

    public function test_breakdowns_report_referrer_device_and_browser(): void
    {
        $this->actAsAdmin();

        PageView::factory()->count(3)->create([
            'referrer' => 'LinkedIn',
            'device' => 'mobile',
            'browser' => 'Safari',
            'platform' => 'iOS',
        ]);

        $response = $this->getJson('/api/v1/page-views/stats')->assertOk();

        $response->assertJsonPath('data.referrers.0.label', 'LinkedIn');
        $response->assertJsonPath('data.referrers.0.views', 3);
        $response->assertJsonPath('data.devices.0.label', 'mobile');
        $response->assertJsonPath('data.browsers.0.label', 'Safari');
        $response->assertJsonPath('data.platforms.0.label', 'iOS');
    }

    public function test_a_visit_with_no_source_is_reported_as_direct(): void
    {
        $this->actAsAdmin();

        PageView::factory()->count(2)->create(['referrer' => null]);

        $this->getJson('/api/v1/page-views/stats')
            ->assertOk()
            ->assertJsonPath('data.referrers.0.label', 'Direct')
            ->assertJsonPath('data.referrers.0.views', 2);
    }

    public function test_recording_classifies_the_visitors_browser_and_device(): void
    {
        $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ])->postJson('/api/v1/page-views', [
            'page' => '/',
            'referrer' => 'https://www.linkedin.com/in/someone/',
        ])->assertCreated();

        $view = PageView::query()->sole();

        $this->assertFalse($view->is_bot);
        $this->assertSame('mobile', $view->device);
        $this->assertSame('Safari', $view->browser);
        $this->assertSame('iOS', $view->platform);
        $this->assertSame('LinkedIn', $view->referrer);
    }

    public function test_recording_flags_a_crawler_that_does_run_javascript(): void
    {
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; GPTBot/1.0; +https://openai.com/gptbot)'])
            ->postJson('/api/v1/page-views', ['page' => '/'])
            ->assertCreated();

        $this->assertTrue(PageView::query()->sole()->is_bot);
    }

    public function test_navigation_within_the_site_is_not_recorded_as_a_referral(): void
    {
        config(['analytics.own_hosts' => ['sobirjonswe.uz']]);

        $this->postJson('/api/v1/page-views', [
            'page' => '/blog',
            'referrer' => 'https://sobirjonswe.uz/projects',
        ])->assertCreated();

        $this->assertNull(
            PageView::query()->sole()->referrer,
            'Our own domain must read as a direct visit, or it tops the referrer list forever.'
        );
    }

    public function test_crawler_stats_are_protected(): void
    {
        $this->getJson('/api/v1/page-views/crawlers')->assertUnauthorized();
    }
}
