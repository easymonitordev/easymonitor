<?php

declare(strict_types=1);

namespace App\Services\MonitoringEngine;

use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\ProbeNode;
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
    public function processResult(array $fields): void
    {
        $monitorId = (int) $fields['check_id'];
        $nodeId = $fields['node'];
        $roundId = $fields['round_id'] ?? null;
        $isUp = (bool) ((int) $fields['ok']);
        $responseTime = isset($fields['ms']) ? (int) $fields['ms'] : null;
        $statusCode = isset($fields['status_code']) ? (int) $fields['status_code'] : null;
        $errorMessage = $fields['error'] ?? null;

        DB::transaction(function () use ($monitorId, $nodeId, $roundId, $isUp, $responseTime, $statusCode, $errorMessage) {
            // Auto-register / refresh the probe (used for quorum N).
            ProbeNode::recordSeen($nodeId);

            // Store the check result.
            CheckResult::create([
                'monitor_id' => $monitorId,
                'node_id' => $nodeId,
                'round_id' => $roundId,
                'is_up' => $isUp,
                'response_time_ms' => $responseTime,
                'status_code' => $statusCode,
                'error_message' => $errorMessage,
            ]);

            $monitor = Monitor::find($monitorId);
            if (! $monitor) {
                return;
            }

            // Always bump "last checked" and capture the latest error so the
            // monitor show page reflects the freshest info from any probe.
            $monitor->last_checked_at = now();
            if (! $isUp) {
                $monitor->last_error = $errorMessage;
            }

            if ($roundId) {
                $this->resolveRound($monitor, $roundId);
            } else {
                // Backward compat: no round_id on this result (old probe or
                // direct dispatch). Fall back to Phase 1 single-observer logic.
                $this->resolveSingle($monitor, $isUp, $errorMessage);
            }

            $monitor->save();
        });
    }

    /**
     * Cross-probe quorum: decide the monitor's status based on all results
     * accumulated so far for this round. Updates the monitor in place and
     * sends notifications on status transitions.
     */
    private function resolveRound(Monitor $monitor, string $roundId): void
    {
        // Skip if we've already decided for this round.
        if ($monitor->last_decided_round_id === $roundId) {
            return;
        }

        $activeProbes = max(1, ProbeNode::activeCount());
        $majority = intdiv($activeProbes, 2) + 1;

        $results = CheckResult::query()
            ->where('monitor_id', $monitor->id)
            ->where('round_id', $roundId)
            ->get();

        $upCount = $results->where('is_up', true)->count();
        $downCount = $results->where('is_up', false)->count();
        $received = $results->count();

        $decision = null;
        if ($downCount >= $majority) {
            $decision = 'down';
        } elseif ($upCount >= $majority) {
            $decision = 'up';
        } elseif ($received >= $activeProbes) {
            // Everyone replied but no majority. Pick the more frequent
            // observation; tie goes to 'up' (benefit of the doubt).
            $decision = $downCount > $upCount ? 'down' : 'up';
        }

        if ($decision === null) {
            // Waiting for more probes to report in this round.
            return;
        }

        $previousStatus = $monitor->status;

        if ($decision === 'up') {
            $wasDown = $previousStatus === 'down';
            $monitor->status = 'up';
            $monitor->last_error = null;
            $monitor->consecutive_failures = 0;
            $monitor->last_decided_round_id = $roundId;

            if ($wasDown) {
                $this->notifyRecovery($monitor);
            }
        } else {
            // down
            $monitor->consecutive_failures = $monitor->consecutive_failures + 1;
            $threshold = $monitor->failure_threshold ?? 1;

            if ($monitor->consecutive_failures >= $threshold && $previousStatus !== 'down') {
                $monitor->status = 'down';
                $this->notifyDown($monitor, $monitor->last_error);
            }

            $monitor->last_decided_round_id = $roundId;
        }
    }

    /**
     * Phase-1 fallback for results without a round_id (legacy probes or
     * results dispatched outside the normal flow).
     */
    private function resolveSingle(Monitor $monitor, bool $isUp, ?string $errorMessage): void
    {
        if ($isUp) {
            $wasDown = $monitor->status === 'down';
            $monitor->status = 'up';
            $monitor->last_error = null;
            $monitor->consecutive_failures = 0;

            if ($wasDown) {
                $this->notifyRecovery($monitor);
            }

            return;
        }

        $monitor->consecutive_failures = $monitor->consecutive_failures + 1;
        $threshold = $monitor->failure_threshold ?? 1;
        $previousStatus = $monitor->status;

        if ($monitor->consecutive_failures >= $threshold && $previousStatus !== 'down') {
            $monitor->status = 'down';
            $this->notifyDown($monitor, $errorMessage);
        }
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
