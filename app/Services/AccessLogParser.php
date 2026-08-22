<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\ServerHitRepositoryInterface;
use App\Support\UserAgentParser;
use Illuminate\Support\Carbon;

/**
 * Rolls nginx's access log up into `server_hit_daily`.
 *
 * This exists because the JavaScript beacon that feeds `page_views` can only
 * see clients that run JavaScript, and most crawlers do not: they fetch the
 * prerendered HTML and leave. No amount of querying `page_views` will surface
 * them — the access log is the only record that they came at all.
 *
 * Counts are absolute, not incremental. Each run re-reads whole days and
 * overwrites them, so running the command twice in an hour, or backfilling
 * after an outage, converges on the same numbers instead of doubling them.
 */
class AccessLogParser
{
    /**
     * nginx's default `combined` format:
     *   ip - user [10/Oct/2026:13:55:36 +0500] "GET /path HTTP/1.1" 200 1234 "ref" "agent"
     *
     * Only the address, the timestamp's date and the agent are captured; the
     * rest is matched loosely so a tweaked log_format does not break parsing.
     */
    private const LINE = '/^(?P<ip>\S+) \S+ \S+ \[(?P<day>\d{2}\/\w{3}\/\d{4}):[^\]]*\] "(?P<method>[A-Z]+)[^"]*" (?P<status>\d{3}) \S+ "[^"]*" "(?P<agent>[^"]*)"/';

    /**
     * A line whose request is not HTTP at all — most often a TLS handshake
     * sent to the plain-HTTP port, which nginx logs as a 400 with the raw
     * bytes where the method should be.
     *
     * These are port scans rather than page requests, so they are counted
     * under their own name instead of being discarded: "nobody is probing
     * this server" and "the parser could not read those lines" are very
     * different things for an admin to see.
     */
    private const MALFORMED = '/^(?P<ip>\S+) \S+ \S+ \[(?P<day>\d{2}\/\w{3}\/\d{4}):/';

    private const MALFORMED_AGENT = 'Malformed request';

    public function __construct(
        private readonly ServerHitRepositoryInterface $repository,
        private readonly UserAgentParser $userAgents,
    ) {}

    /**
     * @param  array<int, string>|null  $paths  Defaults to the configured globs.
     * @return array{files: int, lines: int, rows: int, skipped: int}
     */
    public function parse(?array $paths = null): array
    {
        $paths ??= (array) config('analytics.log_paths', []);
        $limit = max(1, (int) config('analytics.log_line_limit', 500000));

        // Keyed "date|agent" => ['hits' => n, 'ips' => [hash => true], ...].
        // Addresses are held only long enough to count the distinct ones for a
        // day, then discarded — nothing per-address is ever written down.
        $buckets = [];
        $files = 0;
        $lines = 0;
        $skipped = 0;

        foreach ($this->resolve($paths) as $file) {
            $handle = $this->open($file);

            if ($handle === null) {
                continue;
            }

            $files++;

            while (($line = fgets($handle)) !== false) {
                if ($lines >= $limit) {
                    break 2;
                }

                $lines++;

                if (preg_match(self::LINE, $line, $m) === 1) {
                    $parsed = $this->userAgents->parse($m['agent']);
                    $name = $parsed['agent'];
                    $category = $parsed['category'];
                } elseif (preg_match(self::MALFORMED, $line, $m) === 1) {
                    $name = self::MALFORMED_AGENT;
                    $category = 'scanner';
                } else {
                    $skipped++;

                    continue;
                }

                $date = Carbon::createFromFormat('d/M/Y', $m['day'])->format('Y-m-d');
                $key = $date.'|'.$name;

                $buckets[$key] ??= [
                    'date' => $date,
                    'category' => $category,
                    'agent' => $name,
                    'hits' => 0,
                    'ips' => [],
                ];

                $buckets[$key]['hits']++;
                $buckets[$key]['ips'][$m['ip']] = true;
            }

            fclose($handle);
        }

        return [
            'files' => $files,
            'lines' => $lines,
            'skipped' => $skipped,
            'rows' => $this->repository->upsertDaily($this->toRows($buckets)),
        ];
    }

    /**
     * @param  array<string, array{date: string, category: string, agent: string, hits: int, ips: array<string, true>}>  $buckets
     * @return array<int, array{date: string, category: string, agent: string, hits: int, unique_ips: int}>
     */
    private function toRows(array $buckets): array
    {
        $rows = [];

        foreach ($buckets as $bucket) {
            $rows[] = [
                'date' => $bucket['date'],
                'category' => $bucket['category'],
                'agent' => $bucket['agent'],
                'hits' => $bucket['hits'],
                'unique_ips' => count($bucket['ips']),
            ];
        }

        return $rows;
    }

    /**
     * Expand globs and drop anything unreadable, newest file last so that the
     * still-growing access.log is read after the rotations it succeeded.
     *
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    private function resolve(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            foreach (glob($path) ?: [] as $match) {
                if (is_file($match) && is_readable($match)) {
                    $files[$match] = true;
                }
            }
        }

        return array_keys($files);
    }

    /**
     * @return resource|null
     */
    private function open(string $file)
    {
        // The compress.zlib:// wrapper rather than gzopen(): it hands back an
        // ordinary stream, so a single fgets/fclose path covers both the live
        // log and the rotated .gz files. Applied only to .gz — pushing a
        // plain file through zlib would fail rather than pass it through.
        $handle = @fopen(
            str_ends_with($file, '.gz') ? 'compress.zlib://'.$file : $file,
            'rb'
        );

        return is_resource($handle) ? $handle : null;
    }
}
