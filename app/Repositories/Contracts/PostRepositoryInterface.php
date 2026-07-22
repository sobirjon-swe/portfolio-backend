<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Post;
use Illuminate\Database\Eloquent\Collection;

interface PostRepositoryInterface
{
    /**
     * Published posts, newest first (public listing).
     *
     * @return Collection<int, Post>
     */
    public function allPublished(): Collection;

    /**
     * All posts including drafts, newest first (admin listing).
     *
     * @return Collection<int, Post>
     */
    public function all(): Collection;

    public function findPublishedBySlug(string $slug): ?Post;

    public function findById(int $id): ?Post;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Post;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Post $post, array $data): Post;

    public function delete(Post $post): void;
}
