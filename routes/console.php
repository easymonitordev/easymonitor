<?php

use App\Jobs\CheckCertificateExpiry;
use App\Jobs\CheckForUpdates;
use App\Jobs\MonitoringEngine\MonitoringWatchdog;
use Illuminate\Support\Facades\Schedule;

// Watchdog to monitor queue health (runs every minute). Runs on the sync
// connection inside the scheduler: if it were queued, a dead Horizon would
// also silence the watchdog that is meant to report it.
Schedule::job(new MonitoringWatchdog, connection: 'sync')->everyMinute();

// Keep the monitoring Redis Streams bounded (hourly)
Schedule::command('easymonitor:prune-streams')->hourly();

// Refresh the cached latest-release info for the update banner (daily)
Schedule::job(new CheckForUpdates)->daily();

// Alert on TLS certificates approaching expiry (daily)
Schedule::job(new CheckCertificateExpiry)->dailyAt('06:00');

// Horizon snapshot for metrics (every 5 minutes)
Schedule::command('horizon:snapshot')->everyFiveMinutes();
