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
    */

    'retention_days' => (int) env('ANALYTICS_RETENTION_DAYS', 180),

];
