<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\SocialLink;
use Illuminate\Database\Eloquent\Collection;

interface SocialLinkRepositoryInterface
{
    /**
     * @return Collection<int, SocialLink>
     */
    public function all(): Collection;

    public function findById(int $id): ?SocialLink;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SocialLink;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SocialLink $socialLink, array $data): SocialLink;

    public function delete(SocialLink $socialLink): void;
}
