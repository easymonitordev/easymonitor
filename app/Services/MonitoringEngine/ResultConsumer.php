<?php

declare(strict_types=1);

namespace App\Services\MonitoringEngine;

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Notifications\MonitorDown;
use App\Notifications\MonitorRecovered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Consumes check results from Redis Streams and stores them in the database
 *
 * This service reads from the "results" stream, processes check results
 * from probe nodes, updates monitor status, and stores results in TimescaleDB.
 */
class ResultConsumer
{
    /**
     * The Redis stream name for check results
     */
    private const STREAM_RESULTS = 'results';

    /**
     * The consumer group name for result processors
     */
    private const CONSUMER_GROUP = 'result-processors';

    /**
     * The consumer name (unique per worker)
     */
    private string $consumerName;

    /**
     * Create a new ResultConsumer instance
     */
    public function __construct()
    {
        $this->consumerName = 'worker-'.gethostname().'-'.getmypid();
    }

    /**
     * Ensure the consumer group exists for result processors
     */
    public function ensureConsumerGroupExists(): void
    {
        try {
            Redis::connection('streams')->xgroup(
                'CREATE',
                self::STREAM_RESULTS,
                self::CONSUMER_GROUP,
                '0',
                'MKSTREAM'
            );
        } catch (\RedisException $e) {
            if (! str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }

    /**
     * Process pending check results from the stream
     *
     * Reads results from probe nodes, updates monitor status,
     * and stores results in the database.
     *
     * @param  int  $count  Maximum number of results to process
     * @param  int  $blockMs  How long to block waiting for new results
     * @return int Number of results processed
     */
    public function processResults(int $count = 10, int $blockMs = 5000): int
    {
        $this->ensureConsumerGroupExists();

        // XREADGROUP GROUP result-processors worker-1 BLOCK 5000 COUNT 10 STREAMS results >
        $results = Redis::connection('streams')->xreadgroup(
            self::CONSUMER_GROUP,
            $this->consumerName,
            [self::STREAM_RESULTS => '>'],
            $count,
            $blockMs
        );

        if (empty($results)) {
            return 0;
        }

        $processed = 0;

        foreach ($results as $stream => $entries) {
            foreach ($entries as $entryId => $fields) {
                try {
                    $this->processResult($fields);
                    $this->acknowledgeResult($entryId);
                    $processed++;
                } catch (\Exception $e) {
                    Log::error('Failed to process check result', [
                        'entry_id' => $entryId,
                        'fields' => $fields,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $processed;
    }

    /**
     * Process a single check result
     *
     * Stores the result in the database, updates the monitor's status
     * using consecutive failure threshold logic, and sends notifications
     * on status changes.
     *
     * @param  array<string, string>  $fields
     */
    private function processResult(array $fields): void
    {
        $monitorId = (int) $fields['check_id'];
        $nodeId = $fields['node'];
        $isUp = (bool) ((int) $fields['ok']);
        $responseTime = isset($fields['ms']) ? (int) $fields['ms'] : null;
        $statusCode = isset($fields['status_code']) ? (int) $fields['status_code'] : null;
        $errorMessage = $fields['error'] ?? null;

        DB::transaction(function () use ($monitorId, $nodeId, $isUp, $responseTime, $statusCode, $errorMessage) {
            // Store the check result
            CheckResult::create([
                'monitor_id' => $monitorId,
                'node_id' => $nodeId,
                'is_up' => $isUp,
                'response_time_ms' => $responseTime,
                'status_code' => $statusCode,
                'error_message' => $errorMessage,
            ]);

            // Update monitor status with threshold logic
            $monitor = Monitor::find($monitorId);
            if (! $monitor) {
                return;
            }

            $previousStatus = $monitor->status;

            if ($isUp) {
                // Success: reset failures and mark as up
                $wasDown = $monitor->status === 'down';

                $monitor->update([
                    'status' => 'up',
                    'last_checked_at' => now(),
                    'last_error' => null,
                    'consecutive_failures' => 0,
                ]);

                // Send recovery notification if was previously down
                if ($wasDown) {
                    $this->notifyRecovery($monitor);
                }
            } else {
                // Failure: increment counter, only mark down after threshold
                $consecutiveFailures = $monitor->consecutive_failures + 1;
                $threshold = $monitor->failure_threshold ?? 1;

                $newStatus = $consecutiveFailures >= $threshold ? 'down' : $monitor->status;

                $monitor->update([
                    'status' => $newStatus,
                    'last_checked_at' => now(),
                    'last_error' => $errorMessage,
                    'consecutive_failures' => $consecutiveFailures,
                ]);

                // Send down notification only when crossing the threshold
                if ($previousStatus !== 'down' && $newStatus === 'down') {
                    $this->notifyDown($monitor, $errorMessage);
                }
            }
        });
    }

    /**
     * Send a notification that a monitor is down
     */
    private function notifyDown(Monitor $monitor, ?string $errorMessage): void
    {
        try {
            $monitor->user->notify(new MonitorDown($monitor, $errorMessage));

            Log::info('Monitor down notification sent', [
                'monitor_id' => $monitor->id,
                'user_id' => $monitor->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send monitor down notification', [
                'monitor_id' => $monitor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a notification that a monitor has recovered
     */
    private function notifyRecovery(Monitor $monitor): void
    {
        try {
            $monitor->user->notify(new MonitorRecovered($monitor));

            Log::info('Monitor recovery notification sent', [
                'monitor_id' => $monitor->id,
                'user_id' => $monitor->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send monitor recovery notification', [
                'monitor_id' => $monitor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Acknowledge a processed result entry
     */
    private function acknowledgeResult(string $entryId): void
    {
        Redis::connection('streams')->xack(self::STREAM_RESULTS, self::CONSUMER_GROUP, [$entryId]);
    }

    /**
     * Get pending result count in the stream
     */
    public function getPendingResultCount(): int
    {
        try {
            $pending = Redis::connection('streams')->xpending(self::STREAM_RESULTS, self::CONSUMER_GROUP);

            return (int) ($pending[0] ?? 0);
        } catch (\RedisException) {
            return 0;
        }
    }
}
