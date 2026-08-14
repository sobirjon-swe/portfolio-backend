<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Technology;
use App\Repositories\Contracts\TechnologyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TechnologyRepository implements TechnologyRepositoryInterface
{
    public function all(): Collection
    {
        // Strongest first within the group the stack section renders, with
        // the unrated ones after them rather than treated as zero.
        return Technology::query()
            ->orderByRaw('proficiency IS NULL')
            ->orderByDesc('proficiency')
            ->orderBy('name')
            ->get();
    }

    public function findById(int $id): ?Technology
    {
        return Technology::query()->find($id);
    }

    public function create(array $data): Technology
    {
        return Technology::query()->create($data);
    }

    public function update(Technology $technology, array $data): Technology
    {
        $technology->update($data);

        return $technology->refresh();
    }

    public function delete(Technology $technology): void
    {
        $technology->delete();
    }
}
