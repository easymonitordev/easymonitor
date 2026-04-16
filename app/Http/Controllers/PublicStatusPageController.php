<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Incident;
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

        // Flat list of all visible monitors across all sections, ordered by id
        // so the render order stays stable across page refreshes.
        $monitors = $sections->flatMap(fn ($s) => $s['monitors'])->sortBy('id')->values();
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

        $now = now();
        $stats = [];

        foreach ($monitorIds as $monitorId) {
            $oldest = $oldestPerMonitor->get($monitorId);

            if (! $oldest) {
                $stats[$monitorId] = [
                    'uptime' => null,
                    'mode' => 'empty',
                    'from_label' => null,
                    'ticks' => array_fill(0, 60, ['status' => 'none', 'tooltip' => __('No data'), 'up_pct' => 0]),
                ];

                continue;
            }

            $oldestCarbon = Carbon::parse($oldest);

            if ($oldestCarbon->lte($now->copy()->subDays(60))) {
                $stats[$monitorId] = $this->buildDailyTicks($monitorId);
            } elseif ($oldestCarbon->lte($now->copy()->subHours(60))) {
                $stats[$monitorId] = $this->buildHourlyTicks($monitorId);
            } else {
                $stats[$monitorId] = $this->buildMinuteTicks($monitorId);
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
        $start = now()->subDays(59)->startOfDay();

        return $this->buildBucketedTicks(
            monitorId: $monitorId,
            periodStart: $start,
            bucketSeconds: 86400,
            bucketCount: 60,
            tooltipFormat: 'M j',
            fromLabel: $start->diffForHumans(),
            mode: 'daily',
        );
    }

    /**
     * @return array{uptime: float|null, mode: string, ticks: array}
     */
    private function buildHourlyTicks(int $monitorId): array
    {
        $start = now()->subHours(59)->startOfHour();

        return $this->buildBucketedTicks(
            monitorId: $monitorId,
            periodStart: $start,
            bucketSeconds: 3600,
            bucketCount: 60,
            tooltipFormat: 'M j H:i',
            fromLabel: $start->diffForHumans(),
            mode: 'hourly',
        );
    }

    /**
     * @return array{uptime: float|null, mode: string, ticks: array}
     */
    private function buildMinuteTicks(int $monitorId): array
    {
        $start = now()->subMinutes(59)->startOfMinute();

        return $this->buildBucketedTicks(
            monitorId: $monitorId,
            periodStart: $start,
            bucketSeconds: 60,
            bucketCount: 60,
            tooltipFormat: 'H:i',
            fromLabel: $start->diffForHumans(),
            mode: 'minute',
        );
    }

    /**
     * Bucket check_results into a fixed-count timeline. Each tick carries a
     * status (up/partial/down/none) plus the up percentage so the view can
     * render a stacked red/green bar when a bucket is partially degraded.
     *
     * @return array{uptime: float|null, mode: string, from_label: string|null, ticks: array}
     */
    private function buildBucketedTicks(
        int $monitorId,
        Carbon $periodStart,
        int $bucketSeconds,
        int $bucketCount,
        string $tooltipFormat,
        ?string $fromLabel,
        string $mode,
    ): array {
        $rows = DB::table('check_results')
            ->where('monitor_id', $monitorId)
            ->where('created_at', '>=', $periodStart)
            ->selectRaw('
                FLOOR(EXTRACT(EPOCH FROM (created_at - ?)) / ?)::int AS bucket_idx,
                COUNT(*) AS total,
                SUM(CASE WHEN is_up THEN 1 ELSE 0 END) AS up_count,
                SUM(CASE WHEN NOT is_up THEN 1 ELSE 0 END) AS down_count
            ', [$periodStart, $bucketSeconds])
            ->groupBy('bucket_idx')
            ->get()
            ->keyBy('bucket_idx');

        $ticks = [];
        $hasAnyChecks = false;

        for ($i = 0; $i < $bucketCount; $i++) {
            $bucketStart = $periodStart->copy()->addSeconds($i * $bucketSeconds);
            $label = $bucketStart->format($tooltipFormat);
            $row = $rows->get($i);

            if (! $row) {
                $ticks[] = ['status' => 'none', 'tooltip' => $label.': '.__('No data'), 'up_pct' => 0];

                continue;
            }

            $hasAnyChecks = true;
            $total = (int) $row->total;
            $up = (int) $row->up_count;
            $down = (int) $row->down_count;
            $upPct = $total > 0 ? round(($up / $total) * 100, 1) : 0;

            if ($down === 0) {
                $status = 'up';
                $tooltip = $label.': '.trans_choice(':count check ok|:count checks ok', $total, ['count' => $total]);
            } elseif ($up === 0) {
                $status = 'down';
                $tooltip = $label.': '.trans_choice(':count check failed|:count checks failed', $total, ['count' => $total]);
            } else {
                $status = 'partial';
                $tooltip = $label.': '.$down.'/'.$total.' '.__('failed');
            }

            $ticks[] = ['status' => $status, 'tooltip' => $tooltip, 'up_pct' => $upPct];
        }

        return [
            'uptime' => $hasAnyChecks ? $this->uptimeFromIncidents($monitorId, $periodStart) : null,
            'mode' => $mode,
            'from_label' => $fromLabel,
            'ticks' => $ticks,
        ];
    }

    /**
     * Uptime % based on real down-severity incident duration within the period,
     * so single-probe blips that never reached quorum don't drag the number down.
     */
    private function uptimeFromIncidents(int $monitorId, Carbon $periodStart): float
    {
        $periodSeconds = max(1, now()->getTimestamp() - $periodStart->getTimestamp());

        $downSeconds = Incident::query()
            ->where('monitor_id', $monitorId)
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

        return round(max(0, min(100, 100 - (($downSeconds / $periodSeconds) * 100))), 2);
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
