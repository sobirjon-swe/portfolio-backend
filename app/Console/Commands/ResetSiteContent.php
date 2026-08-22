<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetSiteContent extends Command
{
    protected $signature = 'site:reset
                            {--dry-run : Report what would be removed without touching anything}
                            {--force : Skip the confirmation prompt (for non-interactive runs)}';

    protected $description = 'Empty the site of its content, keeping the admin account, the CV and any uploaded files';

    /**
     * What survives, and why. Named explicitly rather than derived by
     * exclusion: a table added later should have to be considered on purpose,
     * not swept away because nobody remembered to exclude it.
     *
     * @var array<string, string>
     */
    private const KEPT = [
        'users' => 'the account that has to log back in afterwards',
        'resumes' => 'the CV, along with its files in storage',
        'migrations' => 'the schema version log — clearing it would strand the database',
        'failed_jobs' => 'empty, and a record of failures rather than content',
        'job_batches' => 'empty',
        'jobs' => 'empty',
        'password_reset_tokens' => 'short-lived, expires on its own',
    ];

    /**
     * Emptied in this order so a row is never left pointing at something that
     * has already gone: children first, then what they belong to.
     *
     * @var array<int, string>
     */
    private const CLEARED = [
        // Attached to a post or project.
        'comments',
        'post_likes',
        'project_technology',
        'images',

        // The content itself.
        'posts',
        'projects',
        'technologies',
        'skills',
        'experiences',
        'awards',
        'news',
        'recommendations',
        'social_links',

        // Correspondence and analytics.
        'messages',
        'page_views',
        'server_hit_daily',
        'page_texts',

        // Sessions: everyone signs in again, nobody is locked out.
        'personal_access_tokens',
        'sessions',

        // Derived, and stale the moment the rows above are gone.
        'cache',
        'cache_locks',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $counts = [];
        $total = 0;

        foreach (self::CLEARED as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $counts[$table] = DB::table($table)->count();
            $total += $counts[$table];
        }

        $this->line('');
        $this->info('Kept:');

        foreach (self::KEPT as $table => $why) {
            $rows = Schema::hasTable($table) ? DB::table($table)->count() : 0;
            $this->line(sprintf('  %-24s %5d  — %s', $table, $rows, $why));
        }

        $this->line('');
        $this->info($dryRun ? 'Would be emptied:' : 'Emptying:');

        foreach ($counts as $table => $rows) {
            $this->line(sprintf('  %-24s %5d', $table, $rows));
        }

        $this->line('');

        if ($dryRun) {
            $this->comment("Dry run — nothing was touched. {$total} row(s) would go.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Remove {$total} row(s)? This cannot be undone without a backup.", false)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        // One transaction: a failure part-way through would otherwise leave
        // the site holding comments whose posts no longer exist.
        DB::transaction(function () use ($counts): void {
            foreach (array_keys($counts) as $table) {
                DB::table($table)->delete();
            }
        });

        $this->resetIdentities(array_keys($counts));

        $this->info("Removed {$total} row(s). The admin account, the CV and every uploaded file are untouched.");

        return self::SUCCESS;
    }

    /**
     * Start ids from 1 again, so the emptied site reads as new rather than as
     * one that lost everything. Postgres only — SQLite reuses ids by itself
     * once a table is empty.
     *
     * @param  array<int, string>  $tables
     */
    private function resetIdentities(array $tables): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tables as $table) {
            // Tables without an id column have no sequence; ask before setting.
            if (! Schema::hasColumn($table, 'id')) {
                continue;
            }

            DB::statement("ALTER SEQUENCE IF EXISTS {$table}_id_seq RESTART WITH 1");
        }
    }
}
