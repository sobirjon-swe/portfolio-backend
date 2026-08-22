<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use DateTimeInterface;

/**
 * Reads the nginx-log rollup in `server_hit_daily`.
 *
 * This is the only view of traffic that does not run JavaScript — crawlers,
 * scanners and scripts — and so the only place most bots are visible at all.
 */
interface ServerHitRepositoryInterface
{
    /**
     * Replace the stored counts for the given day/agent pairs.
     *
     * Absolute values, not increments: the parser re-reads whole days, so
     * running it twice must leave the same numbers rather than double them.
     *
     * @param  array<int, array{date: string, category: string, agent: string, hits: int, unique_ips: int}>  $rows
     * @return int Number of rows written.
     */
    public function upsertDaily(array $rows): int;

    /**
     * Crawler totals per named agent since the given day, busiest first.
     *
     * @return array<int, array{agent: string, category: string, hits: int, unique_ips: int}>
     */
    public function botsSince(DateTimeInterface $since, int $limit): array;

    /**
     * Crawler totals per category since the given day, busiest first.
     *
     * @return array<int, array{category: string, hits: int, unique_ips: int}>
     */
    public function categoriesSince(DateTimeInterface $since): array;

    /**
     * Bot and human hits per calendar day, oldest first.
     *
     * @return array<int, array{date: string, bot_hits: int, human_hits: int}>
     */
    public function dailySince(DateTimeInterface $since): array;

    /**
     * The most recent day the parser has written, or null if it never ran.
     */
    public function lastParsedDate(): ?string;
}
