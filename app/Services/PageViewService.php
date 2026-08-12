<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PageView;
use App\Repositories\Contracts\PageViewRepositoryInterface;
use Illuminate\Support\Carbon;

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
            'ip_hash' => $this->hashIp($ipAddress),
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

    /**
     * Delete analytics older than the configured retention window.
     *
     * @return int Number of rows removed.
     */
    public function prune(?int $days = null): int
    {
        $days ??= (int) config('analytics.retention_days');

        if ($days <= 0) {
            return 0;
        }

        return $this->repository->deleteOlderThan(Carbon::now()->subDays($days));
    }

    /**
     * Turn an IP into a keyed digest.
     *
     * The whole IPv4 space is only ~4 billion addresses, so a plain hash is
     * reversible by brute force in minutes. Keying with APP_KEY means the
     * digests are worthless to anyone without the application secret.
     */
    private function hashIp(?string $ipAddress): ?string
    {
        if ($ipAddress === null || $ipAddress === '') {
            return null;
        }

        return hash_hmac('sha256', $ipAddress, (string) config('app.key'));
    }
}
