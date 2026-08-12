<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PageViewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['page', 'ip_hash', 'user_agent'])]
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
        ];
    }
}
