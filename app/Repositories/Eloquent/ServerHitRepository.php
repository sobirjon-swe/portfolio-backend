<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\ServerHitDaily;
use App\Repositories\Contracts\ServerHitRepositoryInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class ServerHitRepository implements ServerHitRepositoryInterface
{
    public function upsertDaily(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = Carbon::now();

        $rows = array_map(fn (array $row): array => $row + [
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);

        // Chunked because a first run over the full log window can produce
        // several hundred rows, and SQLite caps how many bindings one
        // statement may carry.
        $written = 0;

        foreach (array_chunk($rows, 200) as $chunk) {
            $written += ServerHitDaily::query()->upsert(
                $chunk,
                ['date', 'agent'],
                ['category', 'hits', 'unique_ips', 'updated_at'],
            );
        }

        return $written;
    }

    public function botsSince(DateTimeInterface $since, int $limit): array
    {
        return ServerHitDaily::query()
            ->bots()
            ->where('date', '>=', $this->day($since))
            ->selectRaw('agent, MIN(category) as category, SUM(hits) as hits, SUM(unique_ips) as unique_ips')
            ->groupBy('agent')
            ->orderByDesc('hits')
            ->limit($limit)
            ->get()
            ->map(fn (ServerHitDaily $row): array => [
                'agent' => (string) $row->agent,
                'category' => (string) $row->category,
                'hits' => (int) $row->hits,
                // Summed across days, so a crawler that returns every day is
                // counted once per day rather than once overall. It reads as
                // "distinct addresses seen per day, added up" — the honest
                // figure without keeping raw addresses around.
                'unique_ips' => (int) $row->unique_ips,
            ])
            ->all();
    }

    public function categoriesSince(DateTimeInterface $since): array
    {
        return ServerHitDaily::query()
            ->bots()
            ->where('date', '>=', $this->day($since))
            ->selectRaw('category, SUM(hits) as hits, SUM(unique_ips) as unique_ips')
            ->groupBy('category')
            ->orderByDesc('hits')
            ->get()
            ->map(fn (ServerHitDaily $row): array => [
                'category' => (string) $row->category,
                'hits' => (int) $row->hits,
                'unique_ips' => (int) $row->unique_ips,
            ])
            ->all();
    }

    public function dailySince(DateTimeInterface $since): array
    {
        $rows = ServerHitDaily::query()
            ->where('date', '>=', $this->day($since))
            ->selectRaw('date, category, SUM(hits) as hits')
            ->groupBy('date', 'category')
            ->get();

        $byDate = [];

        foreach ($rows as $row) {
            $date = $row->date instanceof \DateTimeInterface
                ? $row->date->format('Y-m-d')
                : (string) $row->date;

            $byDate[$date] ??= ['date' => $date, 'bot_hits' => 0, 'human_hits' => 0];

            $bucket = in_array($row->category, ServerHitDaily::BOT_CATEGORIES, true) ? 'bot_hits' : 'human_hits';
            $byDate[$date][$bucket] += (int) $row->hits;
        }

        ksort($byDate);

        return array_values($byDate);
    }

    public function lastParsedDate(): ?string
    {
        $latest = ServerHitDaily::query()->max('date');

        if ($latest === null) {
            return null;
        }

        return Carbon::parse($latest)->format('Y-m-d');
    }

    /**
     * `date` is a DATE column, so comparisons must be made against a bare day
     * — passing a full timestamp makes Postgres cast and quietly drop rows.
     */
    private function day(DateTimeInterface $moment): string
    {
        return Carbon::instance(
            $moment instanceof \DateTimeImmutable ? \DateTime::createFromImmutable($moment) : $moment
        )->format('Y-m-d');
    }
}
