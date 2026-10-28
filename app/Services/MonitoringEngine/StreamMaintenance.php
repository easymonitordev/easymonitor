<?php

declare(strict_types=1);

namespace App\Services\MonitoringEngine;

use Illuminate\Support\Facades\Redis;

/**
 * Keeps the monitoring Redis Streams bounded
 *
 * Stream entries are never deleted by XACK, so without trimming the "checks"
 * and "results" streams grow forever. A large dataset makes Redis slow to
 * load on restart, and anything connecting during that window (Horizon,
 * probes) fails with "LOADING Redis is loading the dataset in memory".
 */
class StreamMaintenance
{
    /**
     * The streams that are trimmed to the configured maximum length
     *
     * @var list<string>
     */
    public const STREAMS = ['checks', 'results'];

    /**
     * Trim every monitoring stream to roughly the configured maximum length
     *
     * Uses approximate trimming (MAXLEN ~), which lets Redis drop whole
     * radix tree nodes and keeps the operation cheap. Redis caps each
     * approximate trim at 10000 removed entries by default, so a large
     * backlog is trimmed in repeated calls until nothing more is removed.
     *
     * @return array<string, int> Entries removed, keyed by stream name
     */
    public function trimStreams(): array
    {
        $maxLength = (string) config('easymonitor.streams.max_length');
        $removed = [];

        foreach (self::STREAMS as $stream) {
            $removed[$stream] = 0;

            do {
                $trimmed = (int) Redis::connection('streams')->xtrim($stream, $maxLength, true);
                $removed[$stream] += $trimmed;
            } while ($trimmed > 0);
        }

        return $removed;
    }

    /**
     * Delete consumers of the result-processors group that have been idle
     * longer than the configured threshold
     *
     * Consumers are registered implicitly on their first XREADGROUP and are
     * never removed by Redis, so every worker that ever ran stays listed.
     *
     * @return int Number of consumers deleted
     */
    public function pruneIdleResultConsumers(): int
    {
        $idleThresholdMs = (int) config('easymonitor.streams.consumer_idle_minutes') * 60 * 1000;

        $consumers = Redis::connection('streams')->xinfo('CONSUMERS', ResultConsumer::STREAM_RESULTS, ResultConsumer::CONSUMER_GROUP);

        if (! is_array($consumers)) {
            return 0;
        }

        $deleted = 0;

        foreach ($consumers as $consumer) {
            if ((int) ($consumer['idle'] ?? 0) < $idleThresholdMs) {
                continue;
            }

            Redis::connection('streams')->xgroup('DELCONSUMER', ResultConsumer::STREAM_RESULTS, ResultConsumer::CONSUMER_GROUP, (string) $consumer['name']);
            $deleted++;
        }

        return $deleted;
    }
}
