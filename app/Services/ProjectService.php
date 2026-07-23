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
        $data['slug'] = $this->uniqueSlug($this->titleString($data['title']));

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

        // Regenerate the slug from the default-locale title when it is present.
        if (isset($data['title'])) {
            $data['slug'] = $this->uniqueSlug($this->titleString($data['title']), $project->id);
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
     * Resolve a plain string from a translatable title (default locale first).
     *
     * @param  array<string, string>|string  $title
     */
    private function titleString(array|string $title): string
    {
        if (is_array($title)) {
            $values = array_filter($title, static fn ($v) => is_string($v) && $v !== '');

            return (string) ($title['en'] ?? (array_values($values)[0] ?? ''));
        }

        return $title;
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
