<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AccessLogParser;
use Illuminate\Console\Command;

class ParseAccessLogs extends Command
{
    protected $signature = 'analytics:parse-logs
                            {--path=* : Read these files instead of the configured ones}';

    protected $description = 'Roll the nginx access log up into daily crawler and visitor counts';

    public function handle(AccessLogParser $parser): int
    {
        $paths = $this->option('path');
        $paths = $paths === [] ? null : $paths;

        $result = $parser->parse($paths);

        if ($result['files'] === 0) {
            // Not a failure: the command is scheduled on every environment,
            // including local machines that have no nginx to read.
            $this->warn('No readable access logs found; nothing to parse.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Parsed %d line(s) from %d file(s) into %d daily row(s).',
            $result['lines'],
            $result['files'],
            $result['rows'],
        ));

        if ($result['skipped'] > 0) {
            // Usually the handful of malformed requests every public server
            // receives. A large count means log_format no longer matches.
            $this->line("  {$result['skipped']} line(s) did not match the expected log format.");
        }

        return self::SUCCESS;
    }
}
