<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use Livewire\Component;

class Dashboard extends Component
{
    /**
     * Render the dashboard with monitoring stats
     */
    public function render()
    {
        $user = auth()->user();

        // Get all monitors the user has access to (personal + team)
        $teamIds = $user->ownedTeams->pluck('id')
            ->merge($user->teams->pluck('id'));

        $monitors = Monitor::query()
            ->where(function ($query) use ($user, $teamIds) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('team_id', $teamIds);
            })
            ->with('latestCheckResult')
            ->latest()
            ->get();

        $totalMonitors = $monitors->count();
        $activeMonitors = $monitors->where('is_active', true)->count();
        $monitorsUp = $monitors->where('status', 'up')->count();
        $monitorsDown = $monitors->where('status', 'down')->count();
        $monitorsPending = $monitors->where('status', 'pending')->count();

        // Average response time from last 24h
        $monitorIds = $monitors->pluck('id');
        $rawAvg = CheckResult::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('is_up', true)
            ->where('created_at', '>=', now()->subDay())
            ->avg('response_time_ms');

        $avgResponseTime = $rawAvg !== null ? (int) round((float) $rawAvg) : null;

        // Uptime percentage last 24h
        $totalChecks24h = CheckResult::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $successfulChecks24h = CheckResult::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('is_up', true)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $uptimePercentage = $totalChecks24h > 0
            ? round(($successfulChecks24h / $totalChecks24h) * 100, 2)
            : null;

        // Real incidents (quorum-decided) in the last 24h, plus any still ongoing.
        $incidentsQuery = Incident::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where(function ($q) {
                $q->where('started_at', '>=', now()->subDay())
                  ->orWhereNull('ended_at');
            })
            ->with('monitor')
            ->orderByDesc('started_at');

        $recentIncidents = (clone $incidentsQuery)->limit(10)->get();

        $monitorsDegraded = Incident::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('severity', Incident::SEVERITY_DEGRADED)
            ->whereNull('ended_at')
            ->distinct('monitor_id')
            ->count('monitor_id');

        $downIncidentsCount = (clone $incidentsQuery)
            ->where('severity', Incident::SEVERITY_DOWN)
            ->count();

        $degradedIncidentsCount = (clone $incidentsQuery)
            ->where('severity', Incident::SEVERITY_DEGRADED)
            ->count();

        return view('livewire.dashboard', [
            'monitors' => $monitors,
            'totalMonitors' => $totalMonitors,
            'activeMonitors' => $activeMonitors,
            'monitorsUp' => $monitorsUp,
            'monitorsDown' => $monitorsDown,
            'monitorsDegraded' => $monitorsDegraded,
            'monitorsPending' => $monitorsPending,
            'avgResponseTime' => $avgResponseTime,
            'uptimePercentage' => $uptimePercentage,
            'recentIncidents' => $recentIncidents,
            'downIncidentsCount' => $downIncidentsCount,
            'degradedIncidentsCount' => $degradedIncidentsCount,
        ]);
    }
}
