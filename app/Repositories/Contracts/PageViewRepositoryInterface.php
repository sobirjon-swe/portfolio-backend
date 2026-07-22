<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PageView;

interface PageViewRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): PageView;

    public function totalCount(): int;

    /**
     * Views grouped per page, most-viewed first.
     *
     * @return array<int, array{page: string, views: int}>
     */
    public function countsByPage(): array;
}
