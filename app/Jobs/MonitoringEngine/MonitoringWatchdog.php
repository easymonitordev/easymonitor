<?php

declare(strict_types=1);

namespace App\Jobs\MonitoringEngine;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Watchdog job that monitors the health of monitoring jobs
 *
 * This job runs every minute to check if DispatchMonitorChecks and
 * ProcessMonitorResults are running properly. If a job hasn't run
 * in > 2 minutes, it alerts that the queue may be stalled.
 */
class MonitoringWatchdog implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out
     */
    public int $timeout = 60;

    /**
     * The maximum age (in seconds) before considering a job stalled
     */
    private const STALL_THRESHOLD = 120;

    /**
     * Create a new job instance
     */
    public function __construct()
    {
        $this->onQueue('monitoring');
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $this->checkDispatcherHealth();
        $this->checkResultProcessorHealth();
    }

    /**
     * Check if DispatchMonitorChecks is running
     */
    private function checkDispatcherHealth(): void
    {
        $lastRun = Cache::get('monitor:dispatch-checks:last-run');

        if (! $lastRun) {
            Log::warning('MonitoringWatchdog: DispatchMonitorChecks has never run.');

            return;
        }

        $secondsSinceLastRun = now()->diffInSeconds($lastRun);

        if ($secondsSinceLastRun > self::STALL_THRESHOLD) {
            Log::critical('MonitoringWatchdog: DispatchMonitorChecks appears to be stalled', [
                'last_run' => $lastRun,
                'seconds_since_last_run' => $secondsSinceLastRun,
                'threshold' => self::STALL_THRESHOLD,
            ]);

            // TODO: Send alert notification (email, Slack, etc.)
            // This could integrate with your notification system
        }
    }

    /**
     * Check if ProcessMonitorResults is running
     */
    private function checkResultProcessorHealth(): void
    {
        $lastRun = Cache::get('monitor:process-results:last-run');

        if (! $lastRun) {
            Log::warning('MonitoringWatchdog: ProcessMonitorResults has never run.');

            return;
        }

        $secondsSinceLastRun = now()->diffInSeconds($lastRun);

        if ($secondsSinceLastRun > self::STALL_THRESHOLD) {
            Log::critical('MonitoringWatchdog: ProcessMonitorResults appears to be stalled', [
                'last_run' => $lastRun,
                'seconds_since_last_run' => $secondsSinceLastRun,
                'threshold' => self::STALL_THRESHOLD,
            ]);

            // TODO: Send alert notification (email, Slack, etc.)
        }
    }
}
