<?php

declare(strict_types=1);

namespace App\Livewire\Monitors;

use App\Models\Monitor;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class Show extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public Monitor $monitor;

    public string $period = '24h';

    /**
     * Mount the component and authorize access
     */
    public function mount(Monitor $monitor): void
    {
        $this->authorize('view', $monitor);
        $this->monitor = $monitor;
    }

    /**
     * Reset pagination when period changes
     */
    public function updatedPeriod(): void
    {
        $this->resetPage();
    }

    /**
     * Get the start date for the selected period
     */
    private function getPeriodStart(): \Illuminate\Support\Carbon
    {
        return match ($this->period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subDay(),
        };
    }

    /**
     * Get the bucket interval and time format for the chart based on the period.
     *
     * Bucket size grows to at least 2x the monitor's check interval so each
     * bucket reliably contains at least one check (avoids empty-bucket gaps
     * when checks drift across minute boundaries).
     *
     * @return array{string, string, string, int}
     */
    private function getChartConfig(): array
    {
        $minBucket = max(60, ($this->monitor->check_interval ?? 60) * 2);

        [$dateFormat, $defaultBucket] = match ($this->period) {
            '1h' => ['H:i', 60],
            '24h' => ['H:i', 1800],
            '7d' => ['M d H:i', 10800],
            '30d' => ['M d', 43200],
            default => ['H:i', 1800],
        };

        $bucketSeconds = max($defaultBucket, $minBucket);
        $intervalString = $bucketSeconds.' seconds';

        return ['', $dateFormat, $intervalString, $bucketSeconds];
    }

    /**
     * Build consolidated chart data by averaging results into time buckets
     */
    private function getChartData(\Illuminate\Support\Carbon $periodStart): Collection
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return $this->getChartDataPgsql($periodStart);
        }

        // SQLite fallback (tests)
        return $this->getChartDataSqlite($periodStart);
    }

    /**
     * @return Collection<int, array{time: string, total: int, up: int, down: int, failed_nodes: array<int, string>}>
     */
    private function getChartDataPgsql(\Illuminate\Support\Carbon $periodStart): Collection
    {
        [, $dateFormat, $interval, $bucketSeconds] = $this->getChartConfig();

        $rows = DB::select("
            SELECT
                date_bin(?, created_at, ?) AS bucket,
                COUNT(*) AS total,
                SUM(CASE WHEN is_up THEN 1 ELSE 0 END) AS up_count,
                SUM(CASE WHEN NOT is_up THEN 1 ELSE 0 END) AS down_count,
                STRING_AGG(CASE WHEN NOT is_up THEN node_id END, ',') AS failed_nodes
            FROM check_results
            WHERE monitor_id = ?
              AND created_at >= ?
            GROUP BY bucket
            ORDER BY bucket
        ", [
            $interval,
            $periodStart,
            $this->monitor->id,
            $periodStart,
        ]);

        $byBucket = collect($rows)->keyBy(fn ($row) => \Carbon\Carbon::parse($row->bucket)->getTimestamp());

        return $this->buildBucketTimeline($periodStart, $bucketSeconds, $dateFormat, function (int $ts) use ($byBucket) {
            $row = $byBucket->get($ts);
            if (! $row) {
                return null;
            }

            return [
                'total' => (int) $row->total,
                'up' => (int) $row->up_count,
                'down' => (int) $row->down_count,
                'failed_nodes' => $row->failed_nodes
                    ? array_values(array_unique(array_filter(explode(',', $row->failed_nodes))))
                    : [],
            ];
        });
    }

    /**
     * SQLite fallback for tests: bucket in PHP using the same bucket size as pg.
     *
     * @return Collection<int, array{time: string, total: int, up: int, down: int, failed_nodes: array<int, string>}>
     */
    private function getChartDataSqlite(\Illuminate\Support\Carbon $periodStart): Collection
    {
        [, $dateFormat, , $bucketSeconds] = $this->getChartConfig();
        $startTs = $periodStart->getTimestamp();

        $byBucket = $this->monitor->checkResults()
            ->where('created_at', '>=', $periodStart)
            ->get()
            ->groupBy(fn ($r) => $startTs + (intdiv($r->created_at->getTimestamp() - $startTs, $bucketSeconds) * $bucketSeconds));

        return $this->buildBucketTimeline($periodStart, $bucketSeconds, $dateFormat, function (int $ts) use ($byBucket) {
            $bucket = $byBucket->get($ts);
            if (! $bucket) {
                return null;
            }

            $down = $bucket->where('is_up', false);

            return [
                'total' => $bucket->count(),
                'up' => $bucket->where('is_up', true)->count(),
                'down' => $down->count(),
                'failed_nodes' => $down->pluck('node_id')->unique()->values()->all(),
            ];
        });
    }

    /**
     * Walk every bucket from periodStart to now, calling the loader for each
     * bucket timestamp and filling missing buckets with an empty placeholder.
     *
     * @param  callable(int): ?array{total: int, up: int, down: int, failed_nodes: array<int, string>}  $loader
     * @return Collection<int, array{time: string, total: int, up: int, down: int, failed_nodes: array<int, string>}>
     */
    private function buildBucketTimeline(\Illuminate\Support\Carbon $periodStart, int $bucketSeconds, string $dateFormat, callable $loader): Collection
    {
        $startTs = $periodStart->getTimestamp();
        $endTs = now()->getTimestamp();
        $buckets = collect();

        for ($ts = $startTs; $ts <= $endTs; $ts += $bucketSeconds) {
            $data = $loader($ts);
            $buckets->push([
                'time' => \Carbon\Carbon::createFromTimestamp($ts)->format($dateFormat),
                'total' => $data['total'] ?? 0,
                'up' => $data['up'] ?? 0,
                'down' => $data['down'] ?? 0,
                'failed_nodes' => $data['failed_nodes'] ?? [],
            ]);
        }

        return $buckets;
    }

    /**
     * Render the component
     */
    public function render()
    {
        $periodStart = $this->getPeriodStart();

        // Stats from all results in period
        $statsQuery = $this->monitor->checkResults()
            ->where('created_at', '>=', $periodStart);

        $totalChecks = $statsQuery->count();
        $successfulChecks = (clone $statsQuery)->where('is_up', true)->count();
        $uptimePercentage = $totalChecks > 0
            ? round(($successfulChecks / $totalChecks) * 100, 2)
            : null;

        $rawAvg = (clone $statsQuery)->where('is_up', true)->avg('response_time_ms');
        $avgResponseTime = $rawAvg !== null ? (int) round((float) $rawAvg) : null;
        $minResponseTime = (clone $statsQuery)->where('is_up', true)->min('response_time_ms');
        $maxResponseTime = (clone $statsQuery)->where('is_up', true)->max('response_time_ms');

        // Incidents in the selected period: either started within the window,
        // or started earlier but still ongoing / only recently resolved.
        $incidents = $this->monitor->incidents()
            ->where(function ($q) use ($periodStart) {
                $q->where('started_at', '>=', $periodStart)
                  ->orWhereNull('ended_at')
                  ->orWhere('ended_at', '>=', $periodStart);
            })
            ->orderByDesc('started_at')
            ->limit(50)
            ->get();

        // Consolidated chart data
        $chartData = $this->getChartData($periodStart);

        // Per-node trend + stats for the response-time-by-node table.
        $nodeStats = $this->getNodeStats($periodStart);

        return view('livewire.monitors.show', [
            'incidents' => $incidents,
            'totalChecks' => $totalChecks,
            'uptimePercentage' => $uptimePercentage,
            'avgResponseTime' => $avgResponseTime,
            'minResponseTime' => $minResponseTime,
            'maxResponseTime' => $maxResponseTime,
            'chartData' => $chartData,
            'nodeStats' => $nodeStats,
            'activeProbeCount' => \App\Models\ProbeNode::activeCount(),
        ]);
    }

    /**
     * Build per-node response-time stats and sparkline trend for the period.
     *
     * @return Collection<int, array{node_id: string, min: int, avg: int, max: int, total: int, failures: int, trend: array<int, int|null>}>
     */
    private function getNodeStats(\Illuminate\Support\Carbon $periodStart): Collection
    {
        $driver = DB::connection()->getDriverName();

        // Overall per-node aggregates (up-check response times for min/avg/max;
        // total + failures include down checks so the summary is honest).
        $aggregates = $this->monitor->checkResults()
            ->where('created_at', '>=', $periodStart)
            ->selectRaw('
                node_id,
                COUNT(*) AS total,
                SUM(CASE WHEN is_up THEN 0 ELSE 1 END) AS failures,
                MIN(CASE WHEN is_up THEN response_time_ms END) AS min_ms,
                AVG(CASE WHEN is_up THEN response_time_ms END) AS avg_ms,
                MAX(CASE WHEN is_up THEN response_time_ms END) AS max_ms
            ')
            ->groupBy('node_id')
            ->orderBy('node_id')
            ->get();

        if ($aggregates->isEmpty()) {
            return collect();
        }

        $bucketCount = 40;
        $periodSeconds = max(1, now()->getTimestamp() - $periodStart->getTimestamp());
        $bucketSeconds = (int) max(1, ceil($periodSeconds / $bucketCount));

        $trends = $driver === 'pgsql'
            ? $this->getNodeTrendsPgsql($periodStart, $bucketSeconds)
            : $this->getNodeTrendsSqlite($periodStart, $bucketSeconds);

        return $aggregates->map(function ($row) use ($trends, $periodStart, $bucketSeconds, $bucketCount) {
            $trend = array_fill(0, $bucketCount, null);
            foreach ($trends->get($row->node_id, []) as $bucketIndex => $ms) {
                if ($bucketIndex >= 0 && $bucketIndex < $bucketCount) {
                    $trend[$bucketIndex] = $ms;
                }
            }

            return [
                'node_id' => $row->node_id,
                'total' => (int) $row->total,
                'failures' => (int) $row->failures,
                'min' => $row->min_ms !== null ? (int) round((float) $row->min_ms) : 0,
                'avg' => $row->avg_ms !== null ? (int) round((float) $row->avg_ms) : 0,
                'max' => $row->max_ms !== null ? (int) round((float) $row->max_ms) : 0,
                'trend' => $trend,
            ];
        })->values();
    }

    /**
     * @return Collection<string, array<int, int>> keyed by node_id, values indexed by bucket
     */
    private function getNodeTrendsPgsql(\Illuminate\Support\Carbon $periodStart, int $bucketSeconds): Collection
    {
        $rows = DB::select('
            SELECT
                node_id,
                FLOOR(EXTRACT(EPOCH FROM (created_at - ?)) / ?)::int AS bucket,
                ROUND(AVG(response_time_ms))::int AS avg_ms
            FROM check_results
            WHERE monitor_id = ?
              AND created_at >= ?
              AND is_up = true
            GROUP BY node_id, bucket
            ORDER BY node_id, bucket
        ', [$periodStart, $bucketSeconds, $this->monitor->id, $periodStart]);

        return collect($rows)
            ->groupBy('node_id')
            ->map(fn ($nodeRows) => $nodeRows->mapWithKeys(fn ($r) => [(int) $r->bucket => (int) $r->avg_ms])->all());
    }

    /**
     * @return Collection<string, array<int, int>>
     */
    private function getNodeTrendsSqlite(\Illuminate\Support\Carbon $periodStart, int $bucketSeconds): Collection
    {
        $results = $this->monitor->checkResults()
            ->where('created_at', '>=', $periodStart)
            ->where('is_up', true)
            ->get(['node_id', 'response_time_ms', 'created_at']);

        $startTs = $periodStart->getTimestamp();

        return $results
            ->groupBy('node_id')
            ->map(function ($nodeResults) use ($startTs, $bucketSeconds) {
                return $nodeResults
                    ->groupBy(fn ($r) => (int) floor(($r->created_at->getTimestamp() - $startTs) / $bucketSeconds))
                    ->mapWithKeys(fn ($group, $bucket) => [
                        (int) $bucket => (int) round($group->avg('response_time_ms') ?? 0),
                    ])
                    ->all();
            });
    }

    /**
     * Format a duration in seconds to a short human-readable string.
     */
    public static function formatDuration(?int $seconds): string
    {
        if ($seconds === null || $seconds < 0) {
            return '--';
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;

            return $s === 0 ? "{$m}m" : "{$m}m {$s}s";
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $m === 0 ? "{$h}h" : "{$h}h {$m}m";
    }
}
