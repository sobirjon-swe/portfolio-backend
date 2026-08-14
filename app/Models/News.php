<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NewsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

/**
 * A short, dated announcement — a release, a talk, a milestone.
 *
 * Unlike Post it carries no comments, likes or gallery: those invite a
 * conversation, and an announcement is not one.
 */
#[Fillable(['user_id', 'title', 'slug', 'excerpt', 'content', 'published_at'])]
class News extends Model
{
    /** @use HasFactory<NewsFactory> */
    use HasFactory, HasTranslations;

    /**
     * "News" is already plural, so Eloquent's guess would be right — stated
     * anyway so nobody has to reason about the pluraliser to find the table.
     */
    protected $table = 'news';

    /** @var list<string> */
    public array $translatable = ['title', 'excerpt', 'content'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * Scope to items that are publicly visible (published in the past).
     *
     * @param  Builder<News>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
