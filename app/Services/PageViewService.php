<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PageView;
use App\Repositories\Contracts\PageViewRepositoryInterface;
use App\Support\IpHasher;
use App\Support\ReferrerNormalizer;
use App\Support\UserAgentParser;
use Illuminate\Support\Carbon;

class PageViewService
{
    public function __construct(
        private readonly PageViewRepositoryInterface $repository,
        private readonly IpHasher $ipHasher,
        private readonly UserAgentParser $userAgents,
        private readonly ReferrerNormalizer $referrers,
    ) {}

    /**
     * Record a single visit to a page.
     *
     * The User-Agent is classified on the way in rather than when the
     * dashboard reads. That trades one string scan per visit for not having
     * to parse the whole table on every dashboard load, and it means a later
     * edit to the agent table cannot silently rewrite figures that have
     * already been reported.
     */
    public function record(string $page, ?string $ipAddress, ?string $userAgent, ?string $referrer = null): PageView
    {
        $agent = $this->userAgents->parse($userAgent);

        return $this->repository->record([
            'page' => $page,
            'referrer' => $this->referrers->normalize($referrer),
            'ip_hash' => $this->ipHasher->hash($ipAddress),
            'user_agent' => $userAgent,
            'device' => $agent['device'],
            'browser' => $agent['browser'],
            'platform' => $agent['platform'],
            'is_bot' => $agent['is_bot'],
        ]);
    }

    /**
     * Everything the admin dashboard reports about human traffic.
     *
     * @return array{
     *     total: int,
     *     per_page: array<int, array{page: string, views: int}>,
     *     totals: array<string, int>,
     *     trend: array<int, array{date: string, views: int, visitors: int}>,
     *     top_pages: array<int, array{page: string, views: int, visitors: int}>,
     *     referrers: array<int, array{label: string, views: int, visitors: int}>,
     *     devices: array<int, array{label: string, views: int, visitors: int}>,
     *     browsers: array<int, array{label: string, views: int, visitors: int}>,
     *     platforms: array<int, array{label: string, views: int, visitors: int}>,
     *     window_days: int
     * }
     */
    public function stats(): array
    {
        $days = max(1, (int) config('analytics.trend_days', 30));
        $limit = max(1, (int) config('analytics.breakdown_limit', 10));
        $timezone = (string) config('analytics.display_timezone', 'UTC');

        // Boundaries are found in the admin's zone and handed over in UTC: the
        // column is UTC, but "today" has to mean the day they are living in.
        $today = Carbon::now($timezone)->startOfDay();
        $startOfToday = $today->copy()->utc();
        $sevenDays = $today->copy()->subDays(6)->utc();
        $windowStart = $today->copy()->subDays($days - 1)->utc();

        return [
            // `total` and `per_page` keep the shape the dashboard already
            // consumed, so a browser holding the old admin bundle between the
            // deploy and its next refresh still renders instead of erroring.
            'total' => $this->repository->totalCount(),
            'per_page' => $this->repository->countsByPage(),

            'totals' => [
                'views_today' => $this->repository->viewsSince($startOfToday),
                'visitors_today' => $this->repository->visitorsSince($startOfToday),
                'views_7d' => $this->repository->viewsSince($sevenDays),
                'visitors_7d' => $this->repository->visitorsSince($sevenDays),
                'views_window' => $this->repository->viewsSince($windowStart),
                'visitors_window' => $this->repository->visitorsSince($windowStart),
                'views_all' => $this->repository->totalCount(),
                'visitors_all' => $this->repository->visitorsSince(null),
                'bot_views_window' => $this->repository->botViewsSince($windowStart),
            ],

            'trend' => $this->fillGaps($this->repository->dailyTrend($windowStart), $days, $timezone),
            'top_pages' => $this->repository->topPages($windowStart, $limit),
            'referrers' => $this->repository->breakdown('referrer', $windowStart, $limit, includeNull: true),
            'devices' => $this->repository->breakdown('device', $windowStart, $limit),
            'browsers' => $this->repository->breakdown('browser', $windowStart, $limit),
            'platforms' => $this->repository->breakdown('platform', $windowStart, $limit),
            'window_days' => $days,
        ];
    }

    /**
     * Delete analytics older than the configured retention window.
     *
     * @return int Number of rows removed.
     */
    public function prune(?int $days = null): int
    {
        $days ??= (int) config('analytics.retention_days');

        if ($days <= 0) {
            return 0;
        }

        return $this->repository->deleteOlderThan(Carbon::now()->subDays($days));
    }

    /**
     * A day with no traffic is simply absent from a grouped query, and a chart
     * that skips those days draws a quiet week as a straight line between two
     * busy ones. Zero-filling keeps the horizontal axis honest.
     *
     * @param  array<int, array{date: string, views: int, visitors: int}>  $rows
     * @return array<int, array{date: string, views: int, visitors: int}>
     */
    private function fillGaps(array $rows, int $days, string $timezone): array
    {
        $byDate = [];

        foreach ($rows as $row) {
            $byDate[$row['date']] = $row;
        }

        $filled = [];
        $cursor = Carbon::now($timezone)->startOfDay()->subDays($days - 1);

        for ($i = 0; $i < $days; $i++) {
            $key = $cursor->format('Y-m-d');
            $filled[] = $byDate[$key] ?? ['date' => $key, 'views' => 0, 'visitors' => 0];
            $cursor->addDay();
        }

        return $filled;
    }
}
