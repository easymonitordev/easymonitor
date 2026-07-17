<?php

declare(strict_types=1);

namespace App\Services\MonitoringEngine;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Reads the heartbeat cache keys written by the dispatch and consume loops
 * and decides whether either side of the monitoring engine has stalled.
 *
 * Used by the MonitoringWatchdog (alerts) and the Dashboard (banner).
 */
class EngineHealth
{
    /**
     * The maximum heartbeat age (in seconds) before a component counts as stalled
     */
    public const STALL_THRESHOLD_SECONDS = 120;

    public const STATUS_HEALTHY = 'healthy';

    public const STATUS_STALLED = 'stalled';

    public const STATUS_UNKNOWN = 'unknown';

    public const COMPONENT_DISPATCHER = 'check dispatcher';

    public const COMPONENT_RESULT_CONSUMER = 'result consumer';

    /**
     * The heartbeat cache key and user-facing failure message per component
     *
     * @var array<string, array{cache_key: string, message: string}>
     */
    private const COMPONENTS = [
        self::COMPONENT_DISPATCHER => [
            'cache_key' => 'monitor:dispatch-checks:last-run',
            'message' => 'checks are not being dispatched',
        ],
        self::COMPONENT_RESULT_CONSUMER => [
            'cache_key' => 'monitor:process-results:last-run',
            'message' => 'check results are not being processed',
        ],
    ];

    /**
     * The health status of every engine component
     *
     * @return list<array{component: string, status: string, seconds_since_last_run: ?int, message: string}>
     */
    public function componentStatuses(): array
    {
        $statuses = [];

        foreach (self::COMPONENTS as $component => $definition) {
            $secondsSinceLastRun = $this->secondsSinceLastRun($definition['cache_key']);

            $status = match (true) {
                $secondsSinceLastRun === null => self::STATUS_UNKNOWN,
                $secondsSinceLastRun > self::STALL_THRESHOLD_SECONDS => self::STATUS_STALLED,
                default => self::STATUS_HEALTHY,
            };

            $statuses[] = [
                'component' => $component,
                'status' => $status,
                'seconds_since_last_run' => $secondsSinceLastRun,
                'message' => $definition['message'],
            ];
        }

        return $statuses;
    }

    /**
     * Only the components that are currently stalled
     *
     * @return list<array{component: string, status: string, seconds_since_last_run: ?int, message: string}>
     */
    public function stalledComponents(): array
    {
        return array_values(array_filter(
            $this->componentStatuses(),
            fn (array $status) => $status['status'] === self::STATUS_STALLED,
        ));
    }

    /**
     * The age of a component's heartbeat, or null when it has never run
     * (or its heartbeat cache entry has expired)
     */
    private function secondsSinceLastRun(string $cacheKey): ?int
    {
        $lastRun = Cache::get($cacheKey);

        if (! $lastRun instanceof CarbonInterface) {
            return null;
        }

        return (int) $lastRun->diffInSeconds(now());
    }
}
