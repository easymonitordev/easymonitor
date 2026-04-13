<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\StatusPage;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PublicStatusPageController extends Controller
{
    /**
     * Render the public status page for the given slug.
     *
     * Visibility gate:
     * - public:   anyone can view
     * - unlisted: ?key= must match the access_key
     * - private:  must be authenticated AND owner/team member
     *
     * For unlisted/private misses we 404 — never leak existence.
     */
    public function show(Request $request, string $slug): View
    {
        $statusPage = StatusPage::where('slug', $slug)->first();

        if (! $statusPage) {
            abort(404);
        }

        return $this->renderStatusPage($request, $statusPage);
    }

    /**
     * Resolve the status page when a request hits a verified custom domain.
     * Used by the home route as a host-based fallback.
     */
    public function showByDomain(Request $request): View
    {
        $host = strtolower($request->getHost());

        $statusPage = StatusPage::where('custom_domain', $host)
            ->whereNotNull('domain_verified_at')
            ->first();

        if (! $statusPage) {
            abort(404);
        }

        return $this->renderStatusPage($request, $statusPage);
    }

    /**
     * Caddy on-demand TLS ask endpoint.
     * Caddy hits this before provisioning a cert for a domain.
     * Returns 200 if the domain is a verified custom_domain, else 404.
     */
    public function caddyAsk(Request $request)
    {
        $domain = strtolower(trim((string) $request->query('domain', '')));

        if (! $domain) {
            return response('missing domain', 400);
        }

        $exists = StatusPage::where('custom_domain', $domain)
            ->whereNotNull('domain_verified_at')
            ->exists();

        return $exists ? response('ok', 200) : response('unknown', 404);
    }

    /**
     * Shared rendering for both slug-based and custom-domain routes.
     */
    private function renderStatusPage(Request $request, StatusPage $statusPage): View
    {
        $this->enforceVisibility($request, $statusPage);

        $sections = $statusPage->resolveSections();
        $aggregate = $statusPage->aggregateStatus();
        $activeIncidents = $statusPage->activeIncidents();
        $upcomingMaintenance = $statusPage->upcomingMaintenance();
        $recentIncidents = $statusPage->incidents()
            ->whereNotNull('resolved_at')
            ->limit(10)
            ->get();

        // Flat list of all visible monitors across all sections
        $monitors = $sections->flatMap(fn ($s) => $s['monitors']);
        $monitorStats = $this->computeMonitorStats($monitors->pluck('id')->all());

        return view('public.status', [
            'statusPage' => $statusPage,
            'monitors' => $monitors,
            'monitorStats' => $monitorStats,
            'aggregate' => $aggregate,
            'activeIncidents' => $activeIncidents,
            'upcomingMaintenance' => $upcomingMaintenance,
            'recentIncidents' => $recentIncidents,
        ]);
    }

    /**
     * Build per-monitor stats: a 60-tick timeline + overall uptime percentage.
     *
     * Smart mode selection per monitor:
     * - If the monitor has ≥60 days of history → 60 daily buckets (daily mode)
     * - Otherwise → last 60 individual check results (recent mode)
     *
     * Returns map of monitor_id => [
     *   'uptime' => float|null,
     *   'mode' => 'daily' | 'recent' | 'empty',
     *   'ticks' => array<int, array{status: string, tooltip: string}>
     * ]
     *
     * @param  array<int>  $monitorIds
     */
    private function computeMonitorStats(array $monitorIds): array
    {
        if (empty($monitorIds)) {
            return [];
        }

        // Find oldest check per monitor in one query.
        $oldestPerMonitor = DB::table('check_results')
            ->whereIn('monitor_id', $monitorIds)
            ->select('monitor_id', DB::raw('MIN(created_at) as oldest'))
            ->groupBy('monitor_id')
            ->pluck('oldest', 'monitor_id');

        $sixtyDaysAgo = now()->subDays(60);
        $stats = [];

        foreach ($monitorIds as $monitorId) {
            $oldest = $oldestPerMonitor->get($monitorId);

            if (! $oldest) {
                $stats[$monitorId] = [
                    'uptime' => null,
                    'mode' => 'empty',
                    'from_label' => null,
                    'ticks' => array_fill(0, 60, ['status' => 'none', 'tooltip' => __('No data')]),
                ];

                continue;
            }

            $oldestCarbon = Carbon::parse($oldest);

            if ($oldestCarbon->lt($sixtyDaysAgo)) {
                $stats[$monitorId] = $this->buildDailyTicks($monitorId);
            } else {
                $stats[$monitorId] = $this->buildRecentTicks($monitorId);
            }
        }

        return $stats;
    }

    /**
     * Build 60 daily buckets (most recent on the right).
     *
     * @return array{uptime: float|null, mode: string, ticks: array}
     */
    private function buildDailyTicks(int $monitorId): array
    {
        $start = now()->subDays(60)->startOfDay();

        $rows = DB::table('check_results')
            ->where('monitor_id', $monitorId)
            ->where('created_at', '>=', $start)
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_up THEN 1 ELSE 0 END) as up_count')
            )
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $ticks = [];
        $totalChecks = 0;
        $upChecks = 0;

        for ($i = 59; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->format('Y-m-d');
            $row = $rows->get($key);

            if ($row) {
                $total = (int) $row->total;
                $up = (int) $row->up_count;
                $totalChecks += $total;
                $upChecks += $up;

                if ($up === $total) {
                    $status = 'up';
                    $tooltip = $date->format('M j').' — '.__('No incidents');
                } elseif ($up === 0) {
                    $status = 'down';
                    $tooltip = $date->format('M j').' — '.__('All checks failed').' ('.$total.')';
                } else {
                    $status = 'partial';
                    $pct = round(($up / $total) * 100);
                    $tooltip = $date->format('M j').' — '.$pct.'% '.__('uptime').' ('.($total - $up).' '.__('failed').')';
                }
            } else {
                $status = 'none';
                $tooltip = $date->format('M j').' — '.__('No data');
            }

            $ticks[] = ['status' => $status, 'tooltip' => $tooltip];
        }

        return [
            'uptime' => $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 2) : null,
            'mode' => 'daily',
            'from_label' => __('60 days ago'),
            'ticks' => $ticks,
        ];
    }

    /**
     * Build 60 ticks from the most recent check results (one tick per check).
     * Used when the monitor has less than 60 days of history.
     *
     * @return array{uptime: float|null, mode: string, ticks: array}
     */
    private function buildRecentTicks(int $monitorId): array
    {
        $results = DB::table('check_results')
            ->where('monitor_id', $monitorId)
            ->orderBy('created_at', 'desc')
            ->limit(60)
            ->get(['is_up', 'response_time_ms', 'created_at', 'error_message'])
            ->reverse() // chronological order, oldest -> newest
            ->values();

        $totalChecks = $results->count();
        $upChecks = $results->where('is_up', true)->count();

        // Use the oldest record's timestamp as the "from" label (relative).
        $oldestTimestamp = $results->first()?->created_at;
        $fromLabel = $oldestTimestamp
            ? Carbon::parse($oldestTimestamp)->diffForHumans()
            : null;

        $ticks = [];

        // Pad the start with "no data" placeholders if fewer than 60 results.
        $padding = 60 - $totalChecks;
        for ($i = 0; $i < $padding; $i++) {
            $ticks[] = ['status' => 'none', 'tooltip' => __('No data')];
        }

        foreach ($results as $result) {
            $when = Carbon::parse($result->created_at)->format('M j H:i');
            if ($result->is_up) {
                $rt = $result->response_time_ms ? ' — '.$result->response_time_ms.'ms' : '';
                $ticks[] = ['status' => 'up', 'tooltip' => $when.$rt];
            } else {
                $err = $result->error_message ? ' — '.\Illuminate\Support\Str::limit($result->error_message, 60) : ' — '.__('Failed');
                $ticks[] = ['status' => 'down', 'tooltip' => $when.$err];
            }
        }

        return [
            'uptime' => $totalChecks > 0 ? round(($upChecks / $totalChecks) * 100, 2) : null,
            'mode' => 'recent',
            'from_label' => $fromLabel,
            'ticks' => $ticks,
        ];
    }

    /**
     * Enforce visibility rules — abort 404 on mismatch (don't leak existence).
     */
    private function enforceVisibility(Request $request, StatusPage $statusPage): void
    {
        if ($statusPage->visibility === 'public') {
            return;
        }

        if ($statusPage->visibility === 'unlisted') {
            $providedKey = (string) $request->query('key', '');
            if (! $statusPage->access_key || ! hash_equals($statusPage->access_key, $providedKey)) {
                abort(404);
            }

            return;
        }

        if ($statusPage->visibility === 'private') {
            $user = auth()->user();
            if (! $user) {
                abort(404);
            }

            $allowed = $statusPage->user_id === $user->id;

            if (! $allowed && $statusPage->team_id) {
                $team = $statusPage->team;
                $allowed = $team && ($team->isOwner($user) || $team->hasUser($user));
            }

            if (! $allowed) {
                abort(404);
            }

            return;
        }

        abort(404);
    }
}
