<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Repositories\Contracts\PostRepositoryInterface;
use App\Services\Concerns\GeneratesUniqueSlug;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PostService
{
    use GeneratesUniqueSlug;

    public function __construct(
        private readonly PostRepositoryInterface $repository,
    ) {}

    /**
     * Published posts for public consumption.
     *
     * @return Collection<int, Post>
     */
    public function listPublished(): Collection
    {
        return $this->repository->allPublished();
    }

    /**
     * All posts including drafts (admin listing).
     *
     * @return Collection<int, Post>
     */
    public function listAll(): Collection
    {
        return $this->repository->all();
    }

    public function findById(int $id): Post
    {
        $post = $this->repository->findById($id);

        if ($post === null) {
            throw (new ModelNotFoundException)->setModel(Post::class, [$id]);
        }

        return $post;
    }

    public function findPublishedBySlug(string $slug): Post
    {
        $post = $this->repository->findPublishedBySlug($slug);

        if ($post === null) {
            throw (new ModelNotFoundException)->setModel(Post::class, [$slug]);
        }

        return $post;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $authorId): Post
    {
        $data['user_id'] = $authorId;
        $data['slug'] = $this->uniqueSlugFor(Post::class, $data['title']);

        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Post
    {
        $post = $this->findById($id);

        if (isset($data['title'])) {
            $data['slug'] = $this->uniqueSlugFor(Post::class, $data['title'], $post->id);
        }

        return $this->repository->update($post, $data);
    }

    public function delete(int $id): void
    {
        $post = $this->findById($id);

        $this->repository->delete($post);
    }
}
