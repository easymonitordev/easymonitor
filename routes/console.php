<?php

use App\Jobs\MonitoringEngine\MonitoringWatchdog;
use Illuminate\Support\Facades\Schedule;

// Watchdog to monitor queue health (runs every minute)
Schedule::job(new MonitoringWatchdog)->everyMinute();

// Horizon snapshot for metrics (every 5 minutes)
Schedule::command('horizon:snapshot')->everyFiveMinutes();
