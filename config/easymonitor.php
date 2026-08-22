<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | The version of this EasyMonitor release. Compared against the latest
    | GitHub release to decide whether the update banner should appear.
    |
    */

    'version' => '0.3.0',

    /*
    |--------------------------------------------------------------------------
    | Update Checks
    |--------------------------------------------------------------------------
    |
    | A daily job asks the GitHub releases API for the latest version and
    | shows a dashboard banner to the instance owner when a newer release
    | exists. Set EASYMONITOR_CHECK_UPDATES=false to disable the check
    | entirely (e.g. for air-gapped installs). No instance data is sent.
    |
    */

    'updates' => [
        'enabled' => (bool) env('EASYMONITOR_CHECK_UPDATES', true),
        'repository' => 'easymonitordev/easymonitor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Certificate Expiry Alerts
    |--------------------------------------------------------------------------
    |
    | Days-remaining thresholds at which the certificate expiry alert fires
    | for HTTPS monitors. Each monitor is alerted at most once per threshold;
    | a renewed certificate re-arms the sequence.
    |
    */

    'certificates' => [
        'expiry_alert_days' => [30, 14, 7],
    ],

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
        'rollup_days' => (int) env('CHECK_RESULT_ROLLUP_RETENTION_DAYS', 730),
    ],

];
