<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Support\IpHasher;
use Illuminate\Database\QueryException;

class PostLikeService
{
    public function __construct(
        private readonly IpHasher $ipHasher,
    ) {}

    /**
     * Toggle this visitor's like.
     *
     * @return array{liked: bool, likes_count: int}
     */
    public function toggle(Post $post, ?string $ipAddress): array
    {
        $hash = $this->ipHasher->hash($ipAddress);

        if ($hash === null) {
            // No address to key on — report the count without recording a like
            // rather than letting an unattributable one through.
            return ['liked' => false, 'likes_count' => $post->likes()->count()];
        }

        $existing = $post->likes()->where('ip_hash', $hash)->first();

        if ($existing !== null) {
            $existing->delete();

            return ['liked' => false, 'likes_count' => $post->likes()->count()];
        }

        try {
            $post->likes()->create(['ip_hash' => $hash]);
        } catch (QueryException $e) {
            // Two rapid clicks can race past the check above; the unique index
            // is the real guard, and hitting it means the like already exists.
            if (! $this->isUniqueViolation($e)) {
                throw $e;
            }
        }

        return ['liked' => true, 'likes_count' => $post->likes()->count()];
    }

    public function hasLiked(Post $post, ?string $ipAddress): bool
    {
        $hash = $this->ipHasher->hash($ipAddress);

        return $hash !== null && $post->likes()->where('ip_hash', $hash)->exists();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        // 23505 is PostgreSQL, 23000 covers SQLite and MySQL.
        return in_array($e->getCode(), ['23505', '23000'], true);
    }
}
