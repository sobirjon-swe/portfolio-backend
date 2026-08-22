<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PageViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['page', 'referrer', 'ip_hash', 'user_agent', 'device', 'browser', 'platform', 'is_bot'])]
class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory;

    /**
     * The table only tracks creation time, never updates.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'is_bot' => 'boolean',
        ];
    }

    /**
     * Real people only. Every dashboard figure that claims to count visitors
     * goes through here, so a crawler that does execute JavaScript — some AI
     * agents now do — cannot quietly inflate the numbers.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<PageView>  $query
     */
    public function scopeHumans(Builder $query): void
    {
        $query->where('is_bot', false);
    }
}
