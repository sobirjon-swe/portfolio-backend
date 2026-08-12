<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function all(): Collection
    {
        return Project::query()
            ->with(['technologies', 'images'])
            ->latest()
            ->get();
    }

    public function findById(int $id): ?Project
    {
        return Project::query()
            ->with(['technologies', 'images'])
            ->find($id);
    }

    public function create(array $data, array $technologyIds = []): Project
    {
        $project = Project::query()->create($data);

        $project->technologies()->sync($technologyIds);

        return $project->load(['technologies', 'images']);
    }

    public function update(Project $project, array $data, ?array $technologyIds = null): Project
    {
        $project->update($data);

        if ($technologyIds !== null) {
            $project->technologies()->sync($technologyIds);
        }

        return $project->load('technologies');
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }
}
