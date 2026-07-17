<?php

use App\Jobs\CheckForUpdates;
use App\Jobs\MonitoringEngine\MonitoringWatchdog;
use Illuminate\Support\Facades\Schedule;

// Watchdog to monitor queue health (runs every minute)
Schedule::job(new MonitoringWatchdog)->everyMinute();

// Refresh the cached latest-release info for the update banner (daily)
Schedule::job(new CheckForUpdates)->daily();

// Horizon snapshot for metrics (every 5 minutes)
Schedule::command('horizon:snapshot')->everyFiveMinutes();
