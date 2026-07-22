<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SocialLink;
use App\Repositories\Contracts\SocialLinkRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SocialLinkService
{
    public function __construct(
        private readonly SocialLinkRepositoryInterface $repository,
    ) {}

    /**
     * @return Collection<int, SocialLink>
     */
    public function list(): Collection
    {
        return $this->repository->all();
    }

    public function find(int $id): SocialLink
    {
        $socialLink = $this->repository->findById($id);

        if ($socialLink === null) {
            throw (new ModelNotFoundException)->setModel(SocialLink::class, [$id]);
        }

        return $socialLink;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SocialLink
    {
        return $this->repository->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): SocialLink
    {
        return $this->repository->update($this->find($id), $data);
    }

    public function delete(int $id): void
    {
        $this->repository->delete($this->find($id));
    }
}
