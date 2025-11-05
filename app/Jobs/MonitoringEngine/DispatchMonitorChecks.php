<?php

declare(strict_types=1);

namespace App\Jobs\MonitoringEngine;

use App\Models\Monitor;
use App\Services\MonitoringEngine\CheckDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Self-requeuing job that dispatches monitor checks to Redis Streams
 *
 * This job runs continuously with a 30-second delay between iterations.
 * It uses a cache lock to prevent multiple instances from running simultaneously.
 */
class DispatchMonitorChecks implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out
     */
    public int $timeout = 120;

    /**
     * The number of times the job may be attempted
     */
    public int $tries = 3;

    /**
     * The lock key to prevent duplicate jobs
     */
    private const LOCK_KEY = 'monitor:dispatch-checks:lock';

    /**
     * The lock duration in seconds
     */
    private const LOCK_DURATION = 60;

    /**
     * The delay before requeueing (seconds)
     */
    private const REQUEUE_DELAY = 30;

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
    public function handle(CheckDispatcher $dispatcher): void
    {
        // Acquire lock to prevent duplicate jobs
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_DURATION);

        if (! $lock->get()) {
            Log::warning('DispatchMonitorChecks: Another instance is already running, skipping.');
            $this->requeueJob();

            return;
        }

        try {
            // Ensure consumer group exists
            $dispatcher->ensureConsumerGroupExists();

            // Get monitors that are due for checking
            $monitors = $this->getDueMonitors();

            $dispatched = 0;
            foreach ($monitors as $monitor) {
                try {
                    $dispatcher->dispatchCheck($monitor);

                    // Update next_run_at for this monitor
                    $monitor->update([
                        'next_run_at' => now()->addSeconds($monitor->check_interval),
                    ]);

                    $dispatched++;
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch check for monitor', [
                        'monitor_id' => $monitor->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($dispatched > 0) {
                Log::info("DispatchMonitorChecks: Dispatched {$dispatched} monitor check(s).");
            }

            // Update watchdog timestamp
            Cache::put('monitor:dispatch-checks:last-run', now(), 300);
        } finally {
            $lock->release();
        }

        // Requeue the job for next run
        $this->requeueJob();
    }

    /**
     * Get monitors that are due for checking
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Monitor>
     */
    private function getDueMonitors()
    {
        return Monitor::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();
    }

    /**
     * Requeue this job with a delay
     */
    private function requeueJob(): void
    {
        dispatch(new self())->delay(now()->addSeconds(self::REQUEUE_DELAY));
    }

    /**
     * Handle a job failure
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('DispatchMonitorChecks job failed', [
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);

        // Requeue even on failure to keep the loop running
        $this->requeueJob();
    }
}
