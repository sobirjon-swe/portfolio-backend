<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\SocialLink;
use App\Repositories\Contracts\SocialLinkRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SocialLinkRepository implements SocialLinkRepositoryInterface
{
    public function all(): Collection
    {
        return SocialLink::query()->orderBy('platform')->get();
    }

    public function findById(int $id): ?SocialLink
    {
        return SocialLink::query()->find($id);
    }

    public function create(array $data): SocialLink
    {
        return SocialLink::query()->create($data);
    }

    public function update(SocialLink $socialLink, array $data): SocialLink
    {
        $socialLink->update($data);

        return $socialLink->refresh();
    }

    public function delete(SocialLink $socialLink): void
    {
        $socialLink->delete();
    }
}
