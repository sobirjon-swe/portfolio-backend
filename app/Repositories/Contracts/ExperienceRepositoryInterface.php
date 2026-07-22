<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Experience;
use Illuminate\Database\Eloquent\Collection;

interface ExperienceRepositoryInterface
{
    /**
     * @return Collection<int, Experience>
     */
    public function all(): Collection;

    public function findById(int $id): ?Experience;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Experience;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Experience $experience, array $data): Experience;

    public function delete(Experience $experience): void;
}
