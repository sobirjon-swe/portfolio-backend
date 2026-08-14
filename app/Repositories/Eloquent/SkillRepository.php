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
        // Grouped by area on the site, so hand them over in a stable order
        // rather than one derived from a number that no longer exists.
        return Skill::query()->orderBy('category')->orderBy('id')->get();
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
