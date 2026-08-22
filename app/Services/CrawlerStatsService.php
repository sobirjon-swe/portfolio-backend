<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\ServerHitRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * The crawler half of the admin dashboard, read from the nginx-log rollup.
 *
 * Kept apart from PageViewService because the two measure different things and
 * must not be added together: `page_views` counts people who ran the site's
 * JavaScript, this counts requests that reached the server at all. Presenting
 * one total over both would double-count every human and still miss most bots.
 */
class CrawlerStatsService
{
    /**
     * Categories the dashboard describes in its own words, so the UI does not
     * have to know what a raw category slug means.
     *
     * @var array<string, string>
     */
    private const CATEGORY_LABELS = [
        'search_engine' => 'Search engines',
        'ai_crawler' => 'AI crawlers',
        'seo_crawler' => 'SEO tools',
        'social' => 'Link previews',
        'scanner' => 'Security scanners',
        'tool' => 'Scripts & monitors',
        'other_bot' => 'Other bots',
    ];

    public function __construct(
        private readonly ServerHitRepositoryInterface $repository,
    ) {}

    /**
     * @return array{
     *     available: bool,
     *     last_parsed: string|null,
     *     window_days: int,
     *     totals: array{bot_hits: int, human_hits: int},
     *     categories: array<int, array{category: string, label: string, hits: int, unique_ips: int}>,
     *     agents: array<int, array{agent: string, category: string, hits: int, unique_ips: int}>,
     *     daily: array<int, array{date: string, bot_hits: int, human_hits: int}>
     * }
     */
    public function stats(): array
    {
        $days = max(1, (int) config('analytics.trend_days', 30));
        $limit = max(1, (int) config('analytics.breakdown_limit', 10));
        $timezone = (string) config('analytics.display_timezone', 'UTC');

        $since = Carbon::now($timezone)->startOfDay()->subDays($days - 1);

        $daily = $this->repository->dailySince($since);
        $categories = $this->repository->categoriesSince($since);

        return [
            // The parser has never run, or nginx logs are unreadable. The UI
            // says so rather than drawing an empty panel that reads as "no
            // bots visited", which would be the opposite of the truth.
            'available' => $this->repository->lastParsedDate() !== null,
            'last_parsed' => $this->repository->lastParsedDate(),
            'window_days' => $days,
            'totals' => [
                'bot_hits' => array_sum(array_column($daily, 'bot_hits')),
                'human_hits' => array_sum(array_column($daily, 'human_hits')),
            ],
            'categories' => array_map(fn (array $row): array => $row + [
                'label' => self::CATEGORY_LABELS[$row['category']] ?? $row['category'],
            ], $categories),
            'agents' => $this->repository->botsSince($since, $limit),
            'daily' => $daily,
        ];
    }
}
