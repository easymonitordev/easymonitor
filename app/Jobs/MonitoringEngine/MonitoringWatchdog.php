<?php

declare(strict_types=1);

namespace App\Jobs\MonitoringEngine;

use App\Models\User;
use App\Notifications\MonitoringEngineUnhealthy;
use App\Services\MonitoringEngine\EngineHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Watchdog job that monitors the health of monitoring jobs
 *
 * This job runs every minute to check if DispatchMonitorChecks and
 * ProcessMonitorResults are running properly. If a job hasn't run in
 * > 2 minutes, it alerts the instance owner through their default
 * notification channel (rate-limited to once per hour per component).
 */
class MonitoringWatchdog implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out
     */
    public int $timeout = 60;

    /**
     * How long to suppress repeat alerts for the same component
     */
    private const ALERT_COOLDOWN_SECONDS = 3600;

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
    public function handle(EngineHealth $engineHealth): void
    {
        foreach ($engineHealth->componentStatuses() as $status) {
            match ($status['status']) {
                EngineHealth::STATUS_UNKNOWN => Log::warning(
                    "MonitoringWatchdog: {$status['component']} has never run (or its heartbeat expired)."
                ),
                EngineHealth::STATUS_STALLED => $this->alertStalled($status),
                default => null,
            };
        }
    }

    /**
     * Log and notify the instance owner about a stalled component
     *
     * @param  array{component: string, status: string, seconds_since_last_run: ?int, message: string}  $status
     */
    private function alertStalled(array $status): void
    {
        Log::critical("MonitoringWatchdog: {$status['component']} appears to be stalled", [
            'seconds_since_last_run' => $status['seconds_since_last_run'],
            'threshold' => EngineHealth::STALL_THRESHOLD_SECONDS,
        ]);

        $cooldownKey = 'monitor:watchdog:alerted:'.str_replace(' ', '-', $status['component']);

        if (! Cache::add($cooldownKey, true, self::ALERT_COOLDOWN_SECONDS)) {
            return;
        }

        $channel = User::query()->orderBy('id')->first()?->defaultNotificationChannel();

        if ($channel === null) {
            return;
        }

        // sendNow: the queue may be the very thing that is broken here.
        Notification::sendNow(
            $channel,
            new MonitoringEngineUnhealthy($status['component'], $status['seconds_since_last_run'] ?? 0)
        );
    }
}
