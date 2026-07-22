<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface
{
    /**
     * @return Collection<int, Project>
     */
    public function all(): Collection;

    public function findById(int $id): ?Project;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $technologyIds
     */
    public function create(array $data, array $technologyIds = []): Project;

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>|null  $technologyIds  Null leaves the pivot untouched.
     */
    public function update(Project $project, array $data, ?array $technologyIds = null): Project;

    public function delete(Project $project): void;
}
