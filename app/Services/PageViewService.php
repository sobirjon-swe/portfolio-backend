<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PageView;
use App\Repositories\Contracts\PageViewRepositoryInterface;

class PageViewService
{
    public function __construct(
        private readonly PageViewRepositoryInterface $repository,
    ) {}

    /**
     * Record a single visit to a page.
     */
    public function record(string $page, ?string $ipAddress, ?string $userAgent): PageView
    {
        return $this->repository->record([
            'page' => $page,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Aggregated visit statistics.
     *
     * @return array{total: int, per_page: array<int, array{page: string, views: int}>}
     */
    public function stats(): array
    {
        return [
            'total' => $this->repository->totalCount(),
            'per_page' => $this->repository->countsByPage(),
        ];
    }
}
