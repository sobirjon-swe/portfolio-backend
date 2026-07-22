<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Technology;
use Illuminate\Database\Eloquent\Collection;

interface TechnologyRepositoryInterface
{
    /**
     * @return Collection<int, Technology>
     */
    public function all(): Collection;

    public function findById(int $id): ?Technology;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Technology;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Technology $technology, array $data): Technology;

    public function delete(Technology $technology): void;
}
