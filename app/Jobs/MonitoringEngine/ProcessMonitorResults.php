<?php

declare(strict_types=1);

namespace App\Jobs\MonitoringEngine;

use App\Services\MonitoringEngine\ResultConsumer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Self-requeuing job that processes monitor check results from Redis Streams
 *
 * This job runs continuously with a 5-second delay between iterations.
 * It uses a cache lock to prevent multiple instances from running simultaneously.
 */
class ProcessMonitorResults implements ShouldQueue
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
    private const LOCK_KEY = 'monitor:process-results:lock';

    /**
     * The lock duration in seconds
     */
    private const LOCK_DURATION = 30;

    /**
     * The delay before requeueing (seconds)
     */
    private const REQUEUE_DELAY = 5;

    /**
     * Number of results to process per batch
     */
    private const BATCH_SIZE = 10;

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
    public function handle(ResultConsumer $consumer): void
    {
        // Acquire lock to prevent duplicate jobs
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_DURATION);

        if (! $lock->get()) {
            Log::warning('ProcessMonitorResults: Another instance is already running, skipping.');
            $this->requeueJob();

            return;
        }

        try {
            // Ensure consumer group exists
            $consumer->ensureConsumerGroupExists();

            // Process results (with 5 second block timeout)
            $processed = $consumer->processResults(self::BATCH_SIZE, 5000);

            if ($processed > 0) {
                Log::info("ProcessMonitorResults: Processed {$processed} result(s).");
            }

            // Update watchdog timestamp
            Cache::put('monitor:process-results:last-run', now(), 300);
        } finally {
            $lock->release();
        }

        // Requeue the job for next run
        $this->requeueJob();
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
        Log::error('ProcessMonitorResults job failed', [
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);

        // Requeue even on failure to keep the loop running
        $this->requeueJob();
    }
}
