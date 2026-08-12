<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\PageView;
use DateTimeInterface;

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

    /**
     * Remove every view recorded before the given moment.
     *
     * @return int Number of rows deleted.
     */
    public function deleteOlderThan(DateTimeInterface $cutoff): int;
}
