<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Comment;
use App\Models\Post;
use App\Support\IpHasher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CommentService
{
    public const DEFAULT_PER_PAGE = 20;

    public const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly IpHasher $ipHasher,
        private readonly TelegramNotifier $telegram,
    ) {}

    /**
     * Comments a visitor may read: approved only, oldest first so a thread
     * reads top to bottom.
     *
     * @return Collection<int, Comment>
     */
    public function listApproved(Post $post): Collection
    {
        return $post->comments()->approved()->oldest()->get();
    }

    /**
     * Record a visitor's comment. It stays invisible until approved.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Post $post, array $data, ?string $ipAddress): Comment
    {
        $comment = $post->comments()->create([
            'author_name' => $data['author_name'],
            'body' => $data['body'],
            'is_approved' => false,
            'ip_hash' => $this->ipHasher->hash($ipAddress),
        ]);

        // A pending comment is invisible until it is approved, so without an
        // alert it waits for someone to open the moderation queue.
        $this->telegram->notify('💬 Yangi izoh (tasdiq kutmoqda)', [
            'Muallif' => $comment->author_name,
            'Post' => (string) $post->title,
        ], $comment->body);

        return $comment;
    }

    /**
     * Admin moderation queue.
     *
     * @param  'pending'|'approved'|'all'  $status
     * @return LengthAwarePaginator<int, Comment>
     */
    public function listForModeration(string $status = 'pending', ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage ?? self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE));

        return Comment::query()
            ->with('post:id,slug,title')
            ->when($status === 'pending', fn ($query) => $query->where('is_approved', false))
            ->when($status === 'approved', fn ($query) => $query->where('is_approved', true))
            ->latest()
            ->paginate($perPage);
    }

    public function approve(Comment $comment): Comment
    {
        $comment->update(['is_approved' => true]);

        return $comment->refresh();
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }

    public function pendingCount(): int
    {
        return Comment::query()->where('is_approved', false)->count();
    }
}
