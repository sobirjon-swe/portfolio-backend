<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class ProjectService
{
    public function __construct(
        private readonly ProjectRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, Project>
     */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(int $id): Project
    {
        $project = $this->repository->findById($id);

        if ($project === null) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$id]);
        }

        return $project;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, int $ownerId): Project
    {
        $technologyIds = $this->pullTechnologyIds($data);

        $data['user_id'] = $ownerId;
        $data['slug'] = $this->uniqueSlug($data['title']);

        return $this->repository->create($data, $technologyIds);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Project
    {
        $project = $this->find($id);

        // Only present in the request when the client sends technology_ids.
        $technologyIds = array_key_exists('technology_ids', $data)
            ? $this->pullTechnologyIds($data)
            : null;

        // Regenerate the slug only when the title actually changes.
        if (isset($data['title']) && $data['title'] !== $project->title) {
            $data['slug'] = $this->uniqueSlug($data['title'], $project->id);
        }

        return $this->repository->update($project, $data, $technologyIds);
    }

    public function delete(int $id): void
    {
        $project = $this->find($id);

        $this->repository->delete($project);
    }

    /**
     * Extract and normalise technology_ids out of the payload so it never
     * reaches the projects table as a column.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    private function pullTechnologyIds(array &$data): array
    {
        $ids = $data['technology_ids'] ?? [];
        unset($data['technology_ids']);

        return array_map('intval', (array) $ids);
    }

    /**
     * Build a URL-safe, unique slug from the title.
     */
    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (Project::query()
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
