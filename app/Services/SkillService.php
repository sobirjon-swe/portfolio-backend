<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Skill;
use App\Repositories\Contracts\SkillRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SkillService
{
    public function __construct(
        private readonly SkillRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, Skill>
     */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(int $id): Skill
    {
        $skill = $this->repository->findById($id);

        if ($skill === null) {
            throw (new ModelNotFoundException)->setModel(Skill::class, [$id]);
        }

        return $skill;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Skill
    {
        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): Skill
    {
        return $this->repository->update($this->find($id), $data);
    }

    public function delete(int $id): void
    {
        $this->repository->delete($this->find($id));
    }
}
