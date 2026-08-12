<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LeetCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.leetcode.username', 'testuser');
        Cache::flush();
    }

    /**
     * A successful upstream GraphQL payload.
     *
     * @return array<string, mixed>
     */
    private function upstreamPayload(): array
    {
        return [
            'data' => [
                'matchedUser' => [
                    'username' => 'testuser',
                    'profile' => ['ranking' => 12345],
                    'submitStatsGlobal' => [
                        'acSubmissionNum' => [
                            ['difficulty' => 'All', 'count' => 120],
                            ['difficulty' => 'Easy', 'count' => 60],
                            ['difficulty' => 'Medium', 'count' => 45],
                            ['difficulty' => 'Hard', 'count' => 15],
                        ],
                    ],
                ],
                'allQuestionsCount' => [
                    ['difficulty' => 'All', 'count' => 3000],
                    ['difficulty' => 'Easy', 'count' => 800],
                    ['difficulty' => 'Medium', 'count' => 1600],
                    ['difficulty' => 'Hard', 'count' => 600],
                ],
            ],
        ];
    }

    public function test_it_returns_normalised_stats(): void
    {
        Http::fake(['leetcode.com/graphql' => Http::response($this->upstreamPayload())]);

        $this->getJson('/api/v1/leetcode')
            ->assertOk()
            ->assertJsonPath('data.username', 'testuser')
            ->assertJsonPath('data.ranking', 12345)
            ->assertJsonPath('data.solved.all', 120)
            ->assertJsonPath('data.solved.easy', 60)
            ->assertJsonPath('data.solved.medium', 45)
            ->assertJsonPath('data.solved.hard', 15)
            ->assertJsonPath('data.total.all', 3000);
    }

    public function test_the_endpoint_is_public(): void
    {
        Http::fake(['leetcode.com/graphql' => Http::response($this->upstreamPayload())]);

        // No Authorization header — must not be challenged.
        $this->getJson('/api/v1/leetcode')->assertOk();
    }

    public function test_results_are_cached_so_upstream_is_hit_once(): void
    {
        Http::fake(['leetcode.com/graphql' => Http::response($this->upstreamPayload())]);

        $this->getJson('/api/v1/leetcode')->assertOk();
        $this->getJson('/api/v1/leetcode')->assertOk();
        $this->getJson('/api/v1/leetcode')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_an_unknown_username_yields_502(): void
    {
        // LeetCode answers 200 with a null matchedUser for unknown handles.
        Http::fake(['leetcode.com/graphql' => Http::response(['data' => ['matchedUser' => null]])]);

        $this->getJson('/api/v1/leetcode')->assertStatus(502);
    }

    public function test_an_upstream_error_yields_502(): void
    {
        Http::fake(['leetcode.com/graphql' => Http::response('', 500)]);

        $this->getJson('/api/v1/leetcode')->assertStatus(502);
    }

    public function test_a_connection_failure_yields_502_not_500(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->getJson('/api/v1/leetcode')->assertStatus(502);
    }

    public function test_a_failure_is_not_cached(): void
    {
        // A sequence, not two fake() calls: stubs are matched in registration
        // order, so a second fake() would never take effect.
        Http::fake([
            'leetcode.com/graphql' => Http::sequence()
                ->push('', 500)
                ->push($this->upstreamPayload(), 200),
        ]);

        $this->getJson('/api/v1/leetcode')->assertStatus(502);

        // A transient upstream blip must not pin a 502 for the next six hours.
        $this->getJson('/api/v1/leetcode')
            ->assertOk()
            ->assertJsonPath('data.username', 'testuser');
    }

    public function test_it_returns_404_when_no_username_is_configured(): void
    {
        config()->set('services.leetcode.username', '');

        Http::fake();

        $this->getJson('/api/v1/leetcode')->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_missing_difficulty_rows_default_to_zero(): void
    {
        $payload = $this->upstreamPayload();
        // Upstream sometimes omits difficulties a user has never solved.
        $payload['data']['matchedUser']['submitStatsGlobal']['acSubmissionNum'] = [
            ['difficulty' => 'All', 'count' => 3],
            ['difficulty' => 'Easy', 'count' => 3],
        ];

        Http::fake(['leetcode.com/graphql' => Http::response($payload)]);

        $this->getJson('/api/v1/leetcode')
            ->assertOk()
            ->assertJsonPath('data.solved.easy', 3)
            ->assertJsonPath('data.solved.medium', 0)
            ->assertJsonPath('data.solved.hard', 0);
    }
}
