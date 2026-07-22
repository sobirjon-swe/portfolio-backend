<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Experience;
use App\Repositories\Contracts\ExperienceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ExperienceService
{
    public function __construct(
        private readonly ExperienceRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, Experience>
     */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(int $id): Experience
    {
        $experience = $this->repository->findById($id);

        if ($experience === null) {
            throw (new ModelNotFoundException)->setModel(Experience::class, [$id]);
        }

        return $experience;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Experience
    {
        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Experience
    {
        return $this->repository->update($this->find($id), $data);
    }

    public function delete(int $id): void
    {
        $this->repository->delete($this->find($id));
    }
}
