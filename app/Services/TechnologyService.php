<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Technology;
use App\Repositories\Contracts\TechnologyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TechnologyService
{
    public function __construct(
        private readonly TechnologyRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, Technology>
     */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(int $id): Technology
    {
        $technology = $this->repository->findById($id);

        if ($technology === null) {
            throw (new ModelNotFoundException)->setModel(Technology::class, [$id]);
        }

        return $technology;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Technology
    {
        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Technology
    {
        $technology = $this->find($id);

        return $this->repository->update($technology, $data);
    }

    public function delete(int $id): void
    {
        $technology = $this->find($id);

        $this->repository->delete($technology);
    }
}
