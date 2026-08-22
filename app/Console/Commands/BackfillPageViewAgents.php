<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PageView;
use App\Support\UserAgentParser;
use Illuminate\Console\Command;

class BackfillPageViewAgents extends Command
{
    protected $signature = 'analytics:backfill
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Fill device, browser, platform and is_bot on page views recorded before they were classified';

    /**
     * Rows written before the classifier existed carry a User-Agent and
     * nothing derived from it, so the dashboard's device and browser panels
     * had nothing to show and every one of them counted as a person.
     *
     * The values come from the stored User-Agent, so the pass is idempotent:
     * running it twice reaches the same answer, and running it after the agent
     * table gains an entry corrects rows that entry now recognises.
     */
    public function handle(UserAgentParser $agents): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $scanned = 0;
        $changed = 0;
        $newBots = 0;
        $skipped = 0;

        PageView::query()
            ->orderBy('id')
            // Chunked by id rather than by page: the callback writes to the
            // same table it is reading, and offset paging would skip rows as
            // the result set shifted underneath it.
            ->chunkById(500, function ($rows) use ($agents, $dryRun, &$scanned, &$changed, &$newBots, &$skipped): void {
                foreach ($rows as $row) {
                    $scanned++;

                    $ua = trim((string) $row->user_agent);

                    if ($ua === '') {
                        // No evidence either way. The live parser treats a
                        // missing User-Agent as a bot, but that is a judgement
                        // about a request being made now — for a row already
                        // written, it would be inventing a fact.
                        $skipped++;

                        continue;
                    }

                    $parsed = $agents->parse($ua);

                    $fresh = [
                        'device' => $parsed['device'],
                        'browser' => $parsed['browser'],
                        'platform' => $parsed['platform'],
                        'is_bot' => $parsed['is_bot'],
                    ];

                    $current = [
                        'device' => $row->device,
                        'browser' => $row->browser,
                        'platform' => $row->platform,
                        'is_bot' => (bool) $row->is_bot,
                    ];

                    if ($fresh === $current) {
                        continue;
                    }

                    $changed++;

                    if ($fresh['is_bot'] && ! $current['is_bot']) {
                        $newBots++;
                    }

                    if (! $dryRun) {
                        // Straight to the query builder: no timestamps to
                        // touch on a table that only records creation, and no
                        // model events to fire for a derived-value fill.
                        PageView::query()->whereKey($row->id)->update($fresh);
                    }
                }
            });

        $this->info(sprintf('%s %d row(s), %d would change.', $dryRun ? 'Scanned' : 'Updated', $scanned, $changed));

        if ($newBots > 0) {
            // Worth calling out: these rows counted as people until now, so
            // the visitor figures on the dashboard will drop by this much.
            $this->line("  {$newBots} row(s) reclassified from visitor to crawler.");
        }

        if ($skipped > 0) {
            $this->line("  {$skipped} row(s) had no User-Agent and were left alone.");
        }

        if ($dryRun) {
            $this->comment('Dry run — nothing was written.');
        }

        return self::SUCCESS;
    }
}
