<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\PageViewService;
use Illuminate\Console\Command;

class PrunePageViews extends Command
{
    protected $signature = 'page-views:prune
                            {--days= : Override the configured retention window}';

    protected $description = 'Delete page views older than the analytics retention window';

    public function handle(PageViewService $service): int
    {
        $days = $this->option('days');
        $days = $days === null ? null : (int) $days;

        if ($days !== null && $days < 0) {
            $this->error('--days must be zero or greater.');

            return self::FAILURE;
        }

        $window = $days ?? (int) config('analytics.retention_days');

        if ($window <= 0) {
            $this->info('Retention is disabled (0 days); nothing pruned.');

            return self::SUCCESS;
        }

        $deleted = $service->prune($days);

        $this->info("Pruned {$deleted} page view(s) older than {$window} day(s).");

        return self::SUCCESS;
    }
}
