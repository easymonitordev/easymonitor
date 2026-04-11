<?php

declare(strict_types=1);

namespace App\Services\MonitoringEngine;

use App\Models\CheckResult;
use App\Models\Monitor;
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
     * Stores the result in the database and updates the monitor's status.
     *
     * Expected fields:
     * - check_id: Monitor ID
     * - node: Probe node identifier
     * - ok: 1 or 0 (up or down)
     * - ms: Response time in milliseconds
     * - status_code: HTTP status code (optional)
     * - error: Error message (optional)
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

            // Update monitor status and last_checked_at
            $monitor = Monitor::find($monitorId);
            if ($monitor) {
                $monitor->update([
                    'status' => $isUp ? 'up' : 'down',
                    'last_checked_at' => now(),
                    'last_error' => $isUp ? null : $errorMessage,
                ]);
            }
        });
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
