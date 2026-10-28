<?php

declare(strict_types=1);

namespace App\Services\MonitoringEngine;

use App\Jobs\MonitoringEngine\ProcessMonitorResults;
use Illuminate\Support\Facades\Cache;
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
     * Above this many consumers the group is recreated instead of listed
     */
    private const MAX_CONSUMERS_TO_LIST = 1000;

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
     * Before v0.3.1 each worker process registered its own consumer, which
     * left installs with millions of them; listing those would exhaust PHP's
     * memory, so an oversized group is recreated instead.
     *
     * @return int Number of consumers deleted
     */
    public function pruneIdleResultConsumers(): int
    {
        $group = $this->resultConsumerGroup();

        if ($group === null) {
            return 0;
        }

        if ((int) $group['consumers'] > self::MAX_CONSUMERS_TO_LIST) {
            return $this->recreateResultConsumerGroup($group);
        }

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

    /**
     * The result-processors group as reported by XINFO GROUPS, or null when
     * the results stream or group does not exist yet
     *
     * @return array{name: string, consumers: int, last-delivered-id: string}|null
     */
    private function resultConsumerGroup(): ?array
    {
        $groups = Redis::connection('streams')->xinfo('GROUPS', ResultConsumer::STREAM_RESULTS);

        if (! is_array($groups)) {
            return null;
        }

        foreach ($groups as $group) {
            if (($group['name'] ?? null) === ResultConsumer::CONSUMER_GROUP) {
                return $group;
            }
        }

        return null;
    }

    /**
     * Drop the group with all its consumers and recreate it at the position
     * it had reached, so no unread result is skipped or read twice
     *
     * Holds the result processor's lock so ProcessMonitorResults cannot
     * recreate the group from the start of the stream in between.
     *
     * @param  array{name: string, consumers: int, last-delivered-id: string}  $group
     * @return int Number of consumers deleted
     */
    private function recreateResultConsumerGroup(array $group): int
    {
        return Cache::lock(ProcessMonitorResults::LOCK_KEY, 30)->block(30, function () use ($group): int {
            $redis = Redis::connection('streams');

            $redis->xgroup('DESTROY', ResultConsumer::STREAM_RESULTS, ResultConsumer::CONSUMER_GROUP);
            $redis->xgroup('CREATE', ResultConsumer::STREAM_RESULTS, ResultConsumer::CONSUMER_GROUP, (string) $group['last-delivered-id']);

            return (int) $group['consumers'];
        });
    }
}
