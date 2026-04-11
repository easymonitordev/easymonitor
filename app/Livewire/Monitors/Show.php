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
     * @return array{string, string, string}
     */
    private function getChartConfig(): array
    {
        // [SQL truncation, PHP date format for label, target bucket count ~60]
        return match ($this->period) {
            '1h' => ['minute', 'H:i', '1 minute'],       // ~60 buckets
            '24h' => ['hour', 'H:i', '30 minutes'],      // ~48 buckets
            '7d' => ['hour', 'M d H:i', '3 hours'],      // ~56 buckets
            '30d' => ['day', 'M d', '12 hours'],          // ~60 buckets
            default => ['hour', 'H:i', '30 minutes'],
        };
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
     * @return Collection<int, array{time: string, ms: int, up: bool}>
     */
    private function getChartDataPgsql(\Illuminate\Support\Carbon $periodStart): Collection
    {
        [, $dateFormat, $interval] = $this->getChartConfig();

        $rows = DB::select("
            SELECT
                date_trunc('minute', date_bin(?, created_at, ?)) AS bucket,
                ROUND(AVG(response_time_ms))::int AS avg_ms,
                COUNT(*) AS total,
                SUM(CASE WHEN is_up THEN 1 ELSE 0 END) AS up_count
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

        return collect($rows)->map(fn ($row) => [
            'time' => \Carbon\Carbon::parse($row->bucket)->format($dateFormat),
            'ms' => (int) ($row->avg_ms ?? 0),
            'up' => $row->up_count >= ($row->total / 2), // majority were up
        ]);
    }

    /**
     * SQLite fallback for tests — just grab raw results
     *
     * @return Collection<int, array{time: string, ms: int, up: bool}>
     */
    private function getChartDataSqlite(\Illuminate\Support\Carbon $periodStart): Collection
    {
        return $this->monitor->checkResults()
            ->where('created_at', '>=', $periodStart)
            ->orderBy('created_at')
            ->limit(60)
            ->get()
            ->map(fn ($result) => [
                'time' => $result->created_at->format('H:i'),
                'ms' => $result->response_time_ms ?? 0,
                'up' => $result->is_up,
            ]);
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

        // Paginated check results for the table
        $checkResults = $this->monitor->checkResults()
            ->where('created_at', '>=', $periodStart)
            ->latest()
            ->paginate(15);

        // Consolidated chart data
        $chartData = $this->getChartData($periodStart);

        return view('livewire.monitors.show', [
            'checkResults' => $checkResults,
            'totalChecks' => $totalChecks,
            'uptimePercentage' => $uptimePercentage,
            'avgResponseTime' => $avgResponseTime,
            'minResponseTime' => $minResponseTime,
            'maxResponseTime' => $maxResponseTime,
            'chartData' => $chartData,
        ]);
    }
}
