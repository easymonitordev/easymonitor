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

    'version' => '0.3.2',

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
    | Outbound Notification Targets
    |--------------------------------------------------------------------------
    |
    | Generic webhooks and self-hosted ntfy servers may only point at publicly
    | routable addresses, so users cannot make this server send requests into
    | its own network. Set EASYMONITOR_ALLOW_PRIVATE_NOTIFICATION_TARGETS=true
    | if trusted users need to notify services on your LAN (loopback, private
    | and CGNAT ranges). Link-local addresses such as the cloud metadata
    | endpoint 169.254.169.254 stay blocked either way.
    |
    */

    'outbound' => [
        'allow_private_networks' => (bool) env('EASYMONITOR_ALLOW_PRIVATE_NOTIFICATION_TARGETS', false),
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

    /*
    |--------------------------------------------------------------------------
    | Redis Stream Maintenance
    |--------------------------------------------------------------------------
    |
    | The "checks" and "results" streams are trimmed hourly to roughly
    | "max_length" entries. Processed results are already stored in the
    | database, so older stream entries are only dead weight. Result
    | consumers idle longer than "consumer_idle_minutes" are removed.
    |
    */

    'streams' => [
        'max_length' => (int) env('EASYMONITOR_STREAM_MAX_LENGTH', 10000),
        'consumer_idle_minutes' => (int) env('EASYMONITOR_STREAM_CONSUMER_IDLE_MINUTES', 60),
    ],

];
