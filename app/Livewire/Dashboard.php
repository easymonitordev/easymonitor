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

        // Uptime percentage last 24h — computed from real downtime duration
        // (down-severity incidents), not raw probe success rate, so a single
        // probe blip that never reached quorum doesn't affect the percentage.
        $periodStart = now()->subDay();
        $hasActivity = CheckResult::query()
            ->whereIn('monitor_id', $monitorIds)
            ->where('created_at', '>=', $periodStart)
            ->exists();

        if (! $hasActivity) {
            $uptimePercentage = null;
        } else {
            $periodSeconds = max(1, now()->getTimestamp() - $periodStart->getTimestamp());
            $monitorCount = max(1, $monitorIds->count());

            $downSeconds = Incident::query()
                ->whereIn('monitor_id', $monitorIds)
                ->where('severity', Incident::SEVERITY_DOWN)
                ->where(function ($q) use ($periodStart) {
                    $q->whereNull('ended_at')
                      ->orWhere('ended_at', '>=', $periodStart);
                })
                ->get(['started_at', 'ended_at'])
                ->sum(function ($incident) use ($periodStart) {
                    $start = $incident->started_at->greaterThan($periodStart) ? $incident->started_at : $periodStart;
                    $end = $incident->ended_at ?? now();

                    return max(0, $end->getTimestamp() - $start->getTimestamp());
                });

            $uptimePercentage = round(max(0, min(100, 100 - (($downSeconds / ($periodSeconds * $monitorCount)) * 100))), 2);
        }

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
