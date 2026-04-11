<?php

declare(strict_types=1);

namespace App\Services\MonitoringEngine;

use App\Models\Monitor;
use Illuminate\Support\Facades\Redis;

/**
 * Dispatches monitor checks to Redis Streams for probe nodes to consume
 *
 * This service publishes check jobs to the "checks" stream using XADD.
 * Probe nodes consume from this stream via consumer groups.
 */
class CheckDispatcher
{
    /**
     * The Redis stream name for check jobs
     */
    private const STREAM_CHECKS = 'checks';

    /**
     * The Redis stream name for check results
     */
    private const STREAM_RESULTS = 'results';

    /**
     * The consumer group name for probe nodes
     */
    private const CONSUMER_GROUP = 'probes';

    /**
     * Create a new CheckDispatcher instance
     */
    public function __construct()
    {
        // Use the 'streams' connection which has no prefix
    }

    /**
     * Dispatch all active monitors to the checks stream
     *
     * Only dispatches monitors that are active and due for checking
     * based on their check_interval and last_checked_at timestamp.
     *
     * @return int Number of checks dispatched
     */
    public function dispatchDueChecks(): int
    {
        $monitors = $this->getDueMonitors();
        $dispatched = 0;

        foreach ($monitors as $monitor) {
            $this->dispatchCheck($monitor);
            $dispatched++;
        }

        return $dispatched;
    }

    /**
     * Dispatch a single monitor check to the stream
     *
     * Publishes a check job with the monitor's details to the "checks" stream.
     * Probe nodes will consume this and perform the actual HTTP/ping check.
     */
    public function dispatchCheck(Monitor $monitor): string
    {
        // XADD checks * check_id=42 url=https://... timeout=5000
        $entryId = Redis::connection('streams')->xadd(
            self::STREAM_CHECKS,
            '*', // Auto-generate ID
            [
                'check_id' => (string) $monitor->id,
                'url' => $monitor->url,
                'timeout' => (string) ($monitor->check_interval * 1000), // Convert to milliseconds
            ]
        );

        return $entryId;
    }

    /**
     * Ensure the consumer group exists for probe nodes
     *
     * Creates the consumer group if it doesn't exist. This should be called
     * during application bootstrap or by the first dispatcher run.
     */
    public function ensureConsumerGroupExists(): void
    {
        try {
            // XGROUP CREATE checks probes 0 MKSTREAM
            Redis::connection('streams')->xgroup(
                'CREATE',
                self::STREAM_CHECKS,
                self::CONSUMER_GROUP,
                '0',
                'MKSTREAM'
            );
        } catch (\RedisException $e) {
            // Group already exists, ignore
            if (! str_contains($e->getMessage(), 'BUSYGROUP')) {
                throw $e;
            }
        }
    }

    /**
     * Get all monitors that are due for checking
     *
     * Returns monitors where:
     * - is_active = true
     * - last_checked_at is null OR (now - last_checked_at) >= check_interval
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Monitor>
     */
    private function getDueMonitors()
    {
        return Monitor::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('last_checked_at')
                    ->orWhereRaw('EXTRACT(EPOCH FROM (NOW() - last_checked_at)) >= check_interval');
            })
            ->get();
    }

    /**
     * Get pending check count in the stream
     *
     * Returns the number of pending checks waiting to be processed by probe nodes.
     */
    public function getPendingCheckCount(): int
    {
        try {
            // XPENDING checks probes
            $pending = Redis::connection('streams')->xpending(self::STREAM_CHECKS, self::CONSUMER_GROUP);

            // Returns [count, start_id, end_id, consumers]
            return (int) ($pending[0] ?? 0);
        } catch (\RedisException) {
            return 0;
        }
    }
}
