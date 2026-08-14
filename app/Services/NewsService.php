<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\News;
use App\Repositories\Contracts\NewsRepositoryInterface;
use App\Services\Concerns\GeneratesUniqueSlug;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NewsService
{
    use GeneratesUniqueSlug;

    public function __construct(
        private readonly NewsRepositoryInterface $repository,
    ) {}

    /**
     * Published items for public consumption.
     *
     * @return Collection<int, News>
     */
    public function listPublished(): Collection
    {
        return $this->repository->allPublished();
    }

    /**
     * All items including drafts (admin listing).
     *
     * @return Collection<int, News>
     */
    public function listAll(): Collection
    {
        return $this->repository->all();
    }

    public function findById(int $id): News
    {
        $news = $this->repository->findById($id);

        if ($news === null) {
            throw (new ModelNotFoundException)->setModel(News::class, [$id]);
        }

        return $news;
    }

    public function findPublishedBySlug(string $slug): News
    {
        $news = $this->repository->findPublishedBySlug($slug);

        if ($news === null) {
            throw (new ModelNotFoundException)->setModel(News::class, [$slug]);
        }

        return $news;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $authorId): News
    {
        $data['user_id'] = $authorId;
        $data['slug'] = $this->uniqueSlugFor(News::class, $data['title']);

        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): News
    {
        $news = $this->findById($id);

        if (isset($data['title'])) {
            $data['slug'] = $this->uniqueSlugFor(News::class, $data['title'], $news->id);
        }

        return $this->repository->update($news, $data);
    }

    public function delete(int $id): void
    {
        $news = $this->findById($id);

        $this->repository->delete($news);
    }
}
