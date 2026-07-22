<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Skill;
use App\Repositories\Contracts\SkillRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SkillRepository implements SkillRepositoryInterface
{
    public function all(): Collection
    {
        return Skill::query()->orderByDesc('proficiency')->get();
    }

    public function findById(int $id): ?Skill
    {
        return Skill::query()->find($id);
    }

    public function create(array $data): Skill
    {
        return Skill::query()->create($data);
    }

    public function update(Skill $skill, array $data): Skill
    {
        $skill->update($data);

        return $skill->refresh();
    }

    public function delete(Skill $skill): void
    {
        $skill->delete();
    }
}
