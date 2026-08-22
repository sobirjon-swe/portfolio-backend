<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PageView;
use DateTimeInterface;

/**
 * Every read here reports on people, not requests: crawlers are excluded by
 * the implementation rather than left to each caller to remember. Bot traffic
 * has its own accessor (`botViewsSince`) and its own dashboard panel fed from
 * the server log, because the two answer different questions.
 */
interface PageViewRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): PageView;

    public function totalCount(): int;

    /**
     * Views grouped per page, most-viewed first.
     *
     * @return array<int, array{page: string, views: int}>
     */
    public function countsByPage(): array;

    /**
     * Remove every view recorded before the given moment.
     *
     * @return int Number of rows deleted.
     */
    public function deleteOlderThan(DateTimeInterface $cutoff): int;

    /**
     * Human page views recorded since the given moment, or all time when null.
     */
    public function viewsSince(?DateTimeInterface $since): int;

    /**
     * Distinct human visitors since the given moment, or all time when null.
     */
    public function visitorsSince(?DateTimeInterface $since): int;

    /**
     * Views and visitors per calendar day, oldest first. Days with no traffic
     * are absent — the caller fills the gaps, since only it knows the window.
     *
     * @return array<int, array{date: string, views: int, visitors: int}>
     */
    public function dailyTrend(DateTimeInterface $since): array;

    /**
     * @return array<int, array{page: string, views: int, visitors: int}>
     */
    public function topPages(DateTimeInterface $since, int $limit): array;

    /**
     * Traffic grouped by one of: referrer, device, browser or platform.
     *
     * @param  bool  $includeNull  Count rows with no value under "Direct".
     * @return array<int, array{label: string, views: int, visitors: int}>
     */
    public function breakdown(string $column, DateTimeInterface $since, int $limit, bool $includeNull = false): array;

    /**
     * Bot hits that did reach the API — the minority of crawlers that execute
     * JavaScript. Most never appear here at all; see `server_hit_daily`.
     */
    public function botViewsSince(?DateTimeInterface $since): int;
}
