<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ServerHitDaily;
use App\Models\User;
use App\Services\AccessLogParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The log parser is the only thing that can see crawlers at all, so these
 * cover the two ways it could mislead: missing bots, and inflating them by
 * counting the same lines twice on a repeat run.
 */
class AccessLogParserTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = tempnam(sys_get_temp_dir(), 'accesslog');

        file_put_contents($this->logPath, implode("\n", [
            $this->line('66.249.66.1', '20/Aug/2026', 'GET', 200, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'),
            $this->line('66.249.66.2', '20/Aug/2026', 'GET', 200, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'),
            $this->line('66.249.66.1', '20/Aug/2026', 'GET', 200, 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'),
            $this->line('20.171.207.5', '20/Aug/2026', 'GET', 200, 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot'),
            $this->line('167.94.138.9', '20/Aug/2026', 'GET', 404, 'Mozilla/5.0 (compatible; CensysInspect/1.1; +https://about.censys.io/)'),
            $this->line('84.54.90.10', '21/Aug/2026', 'GET', 200, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36'),
            'this line is not a log line at all',
        ])."\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);

        parent::tearDown();
    }

    private function line(string $ip, string $day, string $method, int $status, string $agent): string
    {
        return sprintf(
            '%s - - [%s:10:15:00 +0500] "%s /projects HTTP/1.1" %d 512 "-" "%s"',
            $ip, $day, $method, $status, $agent
        );
    }

    private function parse(): array
    {
        return app(AccessLogParser::class)->parse([$this->logPath]);
    }

    public function test_it_separates_crawlers_by_name_and_category(): void
    {
        $result = $this->parse();

        $this->assertSame(1, $result['files']);
        $this->assertSame(1, $result['skipped'], 'The malformed line must be counted, not silently dropped.');

        $googlebot = ServerHitDaily::query()->where('agent', 'Googlebot')->sole();
        $this->assertSame('search_engine', $googlebot->category);
        $this->assertSame(3, $googlebot->hits);
        $this->assertSame(2, $googlebot->unique_ips, 'Two addresses, three requests.');

        $this->assertSame('ai_crawler', ServerHitDaily::query()->where('agent', 'GPTBot')->sole()->category);
        $this->assertSame('scanner', ServerHitDaily::query()->where('agent', 'Censys')->sole()->category);
    }

    public function test_a_real_browser_is_recorded_as_human_not_bot(): void
    {
        $this->parse();

        $human = ServerHitDaily::query()->where('category', 'human')->sole();

        $this->assertSame('Human', $human->agent);
        $this->assertSame(1, $human->hits);
    }

    public function test_running_twice_does_not_double_the_counts(): void
    {
        $this->parse();
        $firstPass = ServerHitDaily::query()->sum('hits');

        $this->parse();

        $this->assertSame(
            (int) $firstPass,
            (int) ServerHitDaily::query()->sum('hits'),
            'Counts are absolute: a repeat run must converge, not accumulate.'
        );
    }

    public function test_a_missing_log_is_not_an_error(): void
    {
        $result = app(AccessLogParser::class)->parse(['/nonexistent/path/access.log']);

        $this->assertSame(0, $result['files']);
        $this->assertSame(0, $result['rows']);
    }

    public function test_the_command_reports_what_it_parsed(): void
    {
        $this->artisan('analytics:parse-logs', ['--path' => [$this->logPath]])
            ->expectsOutputToContain('daily row(s)')
            ->assertSuccessful();

        $this->assertGreaterThan(0, ServerHitDaily::query()->count());
    }

    public function test_the_crawler_endpoint_reports_the_parsed_totals(): void
    {
        $this->parse();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/page-views/crawlers')->assertOk();

        $response->assertJsonPath('data.available', true);
        $response->assertJsonPath('data.totals.bot_hits', 5);
        $response->assertJsonPath('data.totals.human_hits', 1);
        $response->assertJsonPath('data.agents.0.agent', 'Googlebot');
    }

    public function test_the_endpoint_says_so_when_the_parser_has_never_run(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/page-views/crawlers')
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.last_parsed', null);
    }
}
