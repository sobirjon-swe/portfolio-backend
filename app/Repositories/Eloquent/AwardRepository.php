<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Award;
use App\Repositories\Contracts\AwardRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AwardRepository implements AwardRepositoryInterface
{
    public function all(): Collection
    {
        return Award::query()
            ->with('images')
            // Newest first once the manual order is exhausted. `issued_on` is
            // free text, but every format I write it in ("2024", "2024-06")
            // sorts correctly as a string, and NULLs land last either way.
            ->orderByDesc('sort_order')
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->get();
    }

    public function findById(int $id): ?Award
    {
        return Award::query()->with('images')->find($id);
    }

    public function create(array $data): Award
    {
        return Award::query()->create($data)->load('images');
    }

    public function update(Award $award, array $data): Award
    {
        $award->update($data);

        return $award->refresh()->load('images');
    }

    public function delete(Award $award): void
    {
        $award->delete();
    }
}
