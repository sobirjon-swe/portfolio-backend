<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\PageView;
use App\Repositories\Contracts\PageViewRepositoryInterface;
use DateTimeInterface;

class PageViewRepository implements PageViewRepositoryInterface
{
    public function record(array $data): PageView
    {
        return PageView::query()->create($data);
    }

    public function totalCount(): int
    {
        return PageView::query()->count();
    }

    public function countsByPage(): array
    {
        return PageView::query()
            ->selectRaw('page, COUNT(*) as views')
            ->groupBy('page')
            ->orderByDesc('views')
            ->get()
            ->map(fn (PageView $row): array => [
                'page' => $row->page,
                'views' => (int) $row->views,
            ])
            ->all();
    }

    public function deleteOlderThan(DateTimeInterface $cutoff): int
    {
        return PageView::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}
