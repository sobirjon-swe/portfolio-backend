<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per day per agent, rolled up from the nginx access log by
 * `analytics:parse-logs`.
 *
 * This is the crawler half of the dashboard. `page_views` covers people —
 * it is fed by a browser calling the API — and cannot see anything that does
 * not run JavaScript, which is most of what visits a public site.
 */
#[Fillable(['date', 'category', 'agent', 'hits', 'unique_ips'])]
class ServerHitDaily extends Model
{
    protected $table = 'server_hit_daily';

    /**
     * Categories that represent an automated client rather than a person.
     *
     * @var array<int, string>
     */
    public const BOT_CATEGORIES = [
        'search_engine',
        'ai_crawler',
        'scanner',
        'seo_crawler',
        'social',
        'tool',
        'other_bot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hits' => 'integer',
            'unique_ips' => 'integer',
        ];
    }

    /**
     * @param  Builder<ServerHitDaily>  $query
     */
    public function scopeBots(Builder $query): void
    {
        $query->whereIn('category', self::BOT_CATEGORIES);
    }
}
