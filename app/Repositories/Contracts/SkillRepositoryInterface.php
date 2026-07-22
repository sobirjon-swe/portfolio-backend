<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Collection;

interface SkillRepositoryInterface
{
    /**
     * @return Collection<int, Skill>
     */
    public function all(): Collection;

    public function findById(int $id): ?Skill;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Skill;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Skill $skill, array $data): Skill;

    public function delete(Skill $skill): void;
}
