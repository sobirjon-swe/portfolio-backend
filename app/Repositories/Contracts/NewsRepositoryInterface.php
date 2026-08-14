<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\News;
use Illuminate\Database\Eloquent\Collection;

interface NewsRepositoryInterface
{
    /**
     * Published items, newest first (public listing).
     *
     * @return Collection<int, News>
     */
    public function allPublished(): Collection;

    /**
     * All items including drafts, newest first (admin listing).
     *
     * @return Collection<int, News>
     */
    public function all(): Collection;

    public function findPublishedBySlug(string $slug): ?News;

    public function findById(int $id): ?News;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): News;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(News $news, array $data): News;

    public function delete(News $news): void;
}
