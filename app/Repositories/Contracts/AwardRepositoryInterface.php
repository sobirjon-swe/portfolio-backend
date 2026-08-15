<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Award;
use Illuminate\Database\Eloquent\Collection;

interface AwardRepositoryInterface
{
    /**
     * @return Collection<int, Award>
     */
    public function all(): Collection;

    public function findById(int $id): ?Award;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Award;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Award $award, array $data): Award;

    public function delete(Award $award): void;
}
