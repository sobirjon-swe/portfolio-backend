<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Award;
use App\Repositories\Contracts\AwardRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AwardService
{
    public function __construct(
        private readonly AwardRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, Award>
     */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(int $id): Award
    {
        $award = $this->repository->findById($id);

        if ($award === null) {
            throw (new ModelNotFoundException)->setModel(Award::class, [$id]);
        }

        return $award;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Award
    {
        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Award
    {
        return $this->repository->update($this->find($id), $data);
    }

    public function delete(int $id): void
    {
        $this->repository->delete($this->find($id));
    }
}
