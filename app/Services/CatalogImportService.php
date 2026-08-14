<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Skill;
use App\Models\Technology;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Adds technologies or skills in one go from the admin's logo picker.
 *
 * Matching is by name, case-insensitively, so re-running the picker tops up the
 * list instead of creating a second "React" next to the existing one. Records
 * that already exist are left exactly as they are — the picker never overwrites
 * a category the user has since edited by hand.
 */
class CatalogImportService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, Technology>
     */
    public function importTechnologies(array $items): Collection
    {
        return $this->import(Technology::class, $items, fn (array $item): array => [
            'name' => $item['name'],
            'icon' => $item['icon'] ?? null,
            'category' => $item['category'] ?? null,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return Collection<int, Skill>
     */
    public function importSkills(array $items): Collection
    {
        return $this->import(Skill::class, $items, fn (array $item): array => [
            'name' => $item['name'],
            'icon' => $item['icon'] ?? null,
            'category' => $item['category'] ?? null,
        ]);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, array<string, mixed>>  $items
     * @param  callable(array<string, mixed>): array<string, mixed>  $toAttributes
     * @return Collection<int, Model>
     */
    private function import(string $model, array $items, callable $toAttributes): Collection
    {
        $existing = $model::query()
            ->pluck('name')
            ->map(fn (string $name): string => mb_strtolower(trim($name)))
            ->flip();

        $created = new Collection;

        DB::transaction(function () use ($model, $items, $toAttributes, $existing, $created): void {
            $seen = [];

            foreach ($items as $item) {
                $key = mb_strtolower(trim((string) $item['name']));

                // Skip both what is already stored and duplicates inside this
                // one payload.
                if ($key === '' || $existing->has($key) || isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $created->push($model::query()->create($toAttributes($item)));
            }
        });

        return $created;
    }
}
