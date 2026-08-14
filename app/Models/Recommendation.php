<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecommendationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'author_name', 'author_role', 'author_company', 'relationship',
    'body', 'linkedin_url', 'is_approved', 'ip_hash',
])]
class Recommendation extends Model
{
    /** @use HasFactory<RecommendationFactory> */
    use HasFactory;

    /**
     * How the author knows me. Shown as a label next to their name, because
     * "a client" and "my manager" carry different weight to different readers.
     *
     * @var list<string>
     */
    public const RELATIONSHIPS = ['client', 'colleague', 'manager', 'other'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
        ];
    }

    /**
     * Recommendations a visitor is allowed to see.
     *
     * @param  Builder<Recommendation>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('is_approved', true);
    }
}
