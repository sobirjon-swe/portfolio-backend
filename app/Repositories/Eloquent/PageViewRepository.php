<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PageView;
use App\Repositories\Contracts\PageViewRepositoryInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PageViewRepository implements PageViewRepositoryInterface
{
    /**
     * Columns the dashboard is allowed to group by. The value reaches a raw
     * SQL fragment, so it is checked against this list rather than trusted.
     *
     * @var array<int, string>
     */
    private const BREAKDOWN_COLUMNS = ['referrer', 'device', 'browser', 'platform'];

    public function record(array $data): PageView
    {
        return PageView::query()->create($data);
    }

    public function totalCount(): int
    {
        return PageView::query()->humans()->count();
    }

    public function countsByPage(): array
    {
        return PageView::query()
            ->humans()
            ->selectRaw('page, COUNT(*) as views')
            ->groupBy('page')
            ->orderByDesc('views')
            ->get()
            ->map(fn (PageView $row): array => [
                'page' => $row->page,
                'views' => (int) $row->views,
            ])
            ->all();
    }

    public function deleteOlderThan(DateTimeInterface $cutoff): int
    {
        return PageView::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    public function viewsSince(?DateTimeInterface $since): int
    {
        return $this->humansSince($since)->count();
    }

    public function visitorsSince(?DateTimeInterface $since): int
    {
        // Keyed on ip_hash, so this counts addresses rather than people: a
        // household behind one router reads as one visitor, and a phone that
        // switches from wifi to mobile data reads as two. It is the closest
        // honest answer available without planting an identifier on anyone.
        return $this->humansSince($since)->distinct()->count('ip_hash');
    }

    public function dailyTrend(DateTimeInterface $since): array
    {
        $day = $this->localDayExpression();

        return PageView::query()
            ->humans()
            ->where('created_at', '>=', $since)
            ->selectRaw("{$day} as day, COUNT(*) as views, COUNT(DISTINCT ip_hash) as visitors")
            ->groupBy(DB::raw($day))
            ->orderBy(DB::raw($day))
            ->get()
            ->map(fn (PageView $row): array => [
                'date' => (string) $row->day,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
            ])
            ->all();
    }

    public function topPages(DateTimeInterface $since, int $limit): array
    {
        return $this->humansSince($since)
            ->selectRaw('page, COUNT(*) as views, COUNT(DISTINCT ip_hash) as visitors')
            ->groupBy('page')
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn (PageView $row): array => [
                'page' => $row->page,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
            ])
            ->all();
    }

    public function breakdown(string $column, DateTimeInterface $since, int $limit, bool $includeNull = false): array
    {
        if (! in_array($column, self::BREAKDOWN_COLUMNS, true)) {
            throw new InvalidArgumentException("Cannot break analytics down by [{$column}].");
        }

        $query = $this->humansSince($since)
            ->selectRaw("{$column} as label, COUNT(*) as views, COUNT(DISTINCT ip_hash) as visitors");

        if (! $includeNull) {
            $query->whereNotNull($column);
        }

        return $query
            ->groupBy($column)
            ->orderByDesc('views')
            ->limit($limit)
            ->get()
            ->map(fn (PageView $row): array => [
                // A null referrer is a visit with no source, which is what
                // "direct" means — it is a real answer, not missing data.
                'label' => $row->label === null || $row->label === '' ? 'Direct' : (string) $row->label,
                'views' => (int) $row->views,
                'visitors' => (int) $row->visitors,
            ])
            ->all();
    }

    public function botViewsSince(?DateTimeInterface $since): int
    {
        $query = PageView::query()->where('is_bot', true);

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        return $query->count();
    }

    /**
     * @return Builder<PageView>
     */
    private function humansSince(?DateTimeInterface $since): Builder
    {
        $query = PageView::query()->humans();

        if ($since !== null) {
            $query->where('created_at', '>=', $since);
        }

        return $query;
    }

    /**
     * A SQL fragment yielding the YYYY-MM-DD of `created_at` in the admin's
     * timezone. Rows are stored in UTC; bucketing them in UTC would put the
     * day boundary at 05:00 local and split every local evening across two
     * bars of the chart.
     *
     * Spelled per driver because there is no portable form. SQLite carries no
     * timezone database and can only be handed a fixed offset, which is exact
     * here because Uzbekistan does not observe daylight saving.
     */
    private function localDayExpression(): string
    {
        $timezone = (string) config('analytics.display_timezone', 'UTC');

        return match (DB::connection()->getDriverName()) {
            'pgsql' => "to_char(created_at AT TIME ZONE 'UTC' AT TIME ZONE ".$this->quote($timezone).", 'YYYY-MM-DD')",
            'mysql', 'mariadb' => 'DATE_FORMAT(CONVERT_TZ(created_at, '."'+00:00', ".$this->quote($this->hourOffset($timezone))."), '%Y-%m-%d')",
            default => "strftime('%Y-%m-%d', created_at, ".$this->quote($this->sqliteOffset($timezone)).')',
        };
    }

    /**
     * The zone's UTC offset as SQLite's date modifier wants it: "+5 hours".
     */
    private function sqliteOffset(string $timezone): string
    {
        return sprintf('%+g hours', $this->offsetSeconds($timezone) / 3600);
    }

    /**
     * The zone's UTC offset as MySQL's CONVERT_TZ wants it: "+05:00".
     */
    private function hourOffset(string $timezone): string
    {
        $seconds = $this->offsetSeconds($timezone);
        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);

        return sprintf('%s%02d:%02d', $sign, intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }

    private function offsetSeconds(string $timezone): int
    {
        return (new \DateTimeZone($timezone))
            ->getOffset(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    private function quote(string $value): string
    {
        return (string) DB::connection()->getPdo()->quote($value);
    }
}
