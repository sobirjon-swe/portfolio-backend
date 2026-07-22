<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Experience;
use App\Repositories\Contracts\ExperienceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ExperienceRepository implements ExperienceRepositoryInterface
{
    public function all(): Collection
    {
        return Experience::query()
            ->orderByDesc('sort_order')
            ->orderByDesc('start_date')
            ->get();
    }

    public function findById(int $id): ?Experience
    {
        return Experience::query()->find($id);
    }

    public function create(array $data): Experience
    {
        return Experience::query()->create($data);
    }

    public function update(Experience $experience, array $data): Experience
    {
        $experience->update($data);

        return $experience->refresh();
    }

    public function delete(Experience $experience): void
    {
        $experience->delete();
    }
}
