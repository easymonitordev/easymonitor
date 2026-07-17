<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Check Result Retention
    |--------------------------------------------------------------------------
    |
    | Raw check results are stored in a TimescaleDB hypertable. Chunks older
    | than "compress_after_days" are compressed, and chunks older than "days"
    | are dropped entirely. Changing these values after installation requires
    | re-applying the policies: php artisan easymonitor:retention
    |
    */

    'retention' => [
        'days' => (int) env('CHECK_RESULT_RETENTION_DAYS', 90),
        'compress_after_days' => (int) env('CHECK_RESULT_COMPRESS_AFTER_DAYS', 7),
    ],

];
