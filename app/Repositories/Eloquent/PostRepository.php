<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PostRepository implements PostRepositoryInterface
{
    public function allPublished(): Collection
    {
        return Post::query()
            ->published()
            ->with('images')
            ->withCount(['likes', 'comments' => fn ($query) => $query->where('is_approved', true)])
            ->latest('published_at')
            ->get();
    }

    public function all(): Collection
    {
        return Post::query()->with('images')
            ->withCount(['likes', 'comments' => fn ($query) => $query->where('is_approved', true)])->latest('created_at')->get();
    }

    public function findPublishedBySlug(string $slug): ?Post
    {
        return Post::query()
            ->published()
            ->with('images')
            ->withCount(['likes', 'comments' => fn ($query) => $query->where('is_approved', true)])
            ->where('slug', $slug)
            ->first();
    }

    public function findById(int $id): ?Post
    {
        return Post::query()->with('images')
            ->withCount(['likes', 'comments' => fn ($query) => $query->where('is_approved', true)])->find($id);
    }

    public function create(array $data): Post
    {
        return Post::query()->create($data);
    }

    public function update(Post $post, array $data): Post
    {
        $post->update($data);

        return $post->refresh()->load('images');
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }
}
