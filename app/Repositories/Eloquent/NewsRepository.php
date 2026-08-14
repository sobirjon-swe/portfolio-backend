<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\News;
use App\Repositories\Contracts\NewsRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class NewsRepository implements NewsRepositoryInterface
{
    public function allPublished(): Collection
    {
        return News::query()
            ->published()
            ->latest('published_at')
            ->get();
    }

    public function all(): Collection
    {
        return News::query()->latest('created_at')->get();
    }

    public function findPublishedBySlug(string $slug): ?News
    {
        return News::query()
            ->published()
            ->where('slug', $slug)
            ->first();
    }

    public function findById(int $id): ?News
    {
        return News::query()->find($id);
    }

    public function create(array $data): News
    {
        return News::query()->create($data);
    }

    public function update(News $news, array $data): News
    {
        $news->update($data);

        return $news->refresh();
    }

    public function delete(News $news): void
    {
        $news->delete();
    }
}
