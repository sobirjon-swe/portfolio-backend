<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page View Retention
    |--------------------------------------------------------------------------
    |
    | How many days of raw page_views rows to keep. The `page-views:prune`
    | command deletes anything older and is scheduled daily, which stops the
    | table (and the admin stats aggregation over it) from growing without
    | bound. Set to 0 to disable pruning entirely.
    |
    | `server_hit_daily` is deliberately never pruned: it holds one small row
    | per day per crawler, so its whole history costs less than a day of raw
    | views.
    |
    */

    'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 180),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Window
    |--------------------------------------------------------------------------
    |
    | How many days the dashboard's trend chart covers, and how many rows each
    | breakdown (top pages, referrers, browsers) returns.
    |
    */

    'trend_days' => (int) env('ANALYTICS_TREND_DAYS', 30),

    'breakdown_limit' => (int) env('ANALYTICS_BREAKDOWN_LIMIT', 10),

    /*
    |--------------------------------------------------------------------------
    | Display Timezone
    |--------------------------------------------------------------------------
    |
    | Timestamps are stored in UTC, which is right for storage and wrong for
    | reading: at UTC+5 a "today" bucketed in UTC starts at 05:00 local and
    | swallows the previous evening. The dashboard buckets days in this zone
    | instead, so "today" on the chart is the day the admin is living in.
    |
    | Nginx writes its access log in the server's local time, so the log
    | parser reads that zone rather than this one.
    |
    */

    'display_timezone' => (string) env('ANALYTICS_TIMEZONE', 'Asia/Tashkent'),

    /*
    |--------------------------------------------------------------------------
    | Own Hosts
    |--------------------------------------------------------------------------
    |
    | Referrers from these hosts are recorded as direct visits. Without this
    | the site's own domain would top the referrer list on every route change,
    | drowning out the external sources the list exists to surface.
    |
    */

    'own_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ANALYTICS_OWN_HOSTS', 'sobirjonswe.uz,api.sobirjonswe.uz,localhost'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Nginx Access Logs
    |--------------------------------------------------------------------------
    |
    | Crawlers do not run JavaScript, so they never reach the page-views
    | endpoint and cannot appear in `page_views` however it is queried. The
    | access log is the only place they show up, so `analytics:parse-logs`
    | reads these files and rolls them into `server_hit_daily`, which is what
    | the dashboard's crawler panel reports.
    |
    | Globs are resolved at run time and missing files are skipped, so the
    | defaults are harmless on a machine with no nginx (local development).
    | The PHP-FPM user must be able to read them — on Ubuntu nginx writes the
    | logs as www-data, which is that same user, so no extra grant is needed.
    |
    */

    'log_paths' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'ANALYTICS_LOG_PATHS',
            '/var/log/nginx/access.log,/var/log/nginx/access.log.1,/var/log/nginx/access.log.*.gz'
        ))
    ))),

    /*
    | Guard against a runaway log: the parser stops after this many lines in a
    | single run rather than holding an unbounded set of addresses in memory.
    */

    'log_line_limit' => (int) env('ANALYTICS_LOG_LINE_LIMIT', 500000),

];
