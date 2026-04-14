<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Monitor;
use App\Models\ProbeNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Public health/status endpoint.
 *
 * Shows operators whether the whole monitoring pipeline is healthy —
 * DB connectivity, Redis, queue worker (Horizon), and the monitoring
 * dispatch/result-processing loop.
 *
 * Returns JSON by default; rich HTML if the browser sent an Accept header
 * that prefers text/html.
 *
 * GET /healthz
 *   - 200 when everything is operational
 *   - 503 when at least one critical component is down
 *
 * Intended to be curl-friendly for external uptime monitors without
 * exposing anything sensitive.
 */
class HealthController extends Controller
{
    /**
     * Anything older than this and a "last seen" timestamp is considered stale.
     */
    private const STALE_SECONDS = 120;

    public function __invoke(Request $request): Response|JsonResponse
    {
        $checks = $this->runChecks();
        $overall = collect($checks)->contains(fn ($c) => $c['status'] === 'fail') ? 'fail' : 'ok';

        $httpStatus = $overall === 'ok' ? 200 : 503;

        $payload = [
            'status' => $overall,
            'timestamp' => now()->toIso8601String(),
            'components' => $checks,
            'stats' => [
                'active_monitors' => $this->safeCount(fn () => Monitor::where('is_active', true)->count()),
                'active_probes' => $this->safeCount(fn () => ProbeNode::activeCount()),
                'total_monitors' => $this->safeCount(fn () => Monitor::count()),
            ],
        ];

        if ($this->prefersJson($request)) {
            return response()->json($payload, $httpStatus);
        }

        return response()->view('health', $payload, $httpStatus);
    }

    /**
     * @return array<string, array{status: string, detail: string}>
     */
    private function runChecks(): array
    {
        return [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'monitoring_loop' => $this->checkMonitoringLoop(),
            'probes' => $this->checkProbes(),
        ];
    }

    /**
     * @return array{status: string, detail: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();

            return ['status' => 'ok', 'detail' => "connected via {$driver}"];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => 'cannot connect: '.$e->getMessage()];
        }
    }

    /**
     * @return array{status: string, detail: string}
     */
    private function checkRedis(): array
    {
        try {
            $ping = Redis::connection()->ping();
            if (! $ping) {
                return ['status' => 'fail', 'detail' => 'ping returned falsy'];
            }

            return ['status' => 'ok', 'detail' => 'connected'];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => 'cannot connect: '.$e->getMessage()];
        }
    }

    /**
     * The dispatcher and result-processor jobs each set a cache key on every run.
     * If we haven't seen either within STALE_SECONDS, the loop has stalled.
     *
     * @return array{status: string, detail: string}
     */
    private function checkMonitoringLoop(): array
    {
        try {
            $dispatchLast = Cache::get('monitor:dispatch-checks:last-run');
            $resultsLast = Cache::get('monitor:process-results:last-run');

            if (! $dispatchLast || ! $resultsLast) {
                return [
                    'status' => 'fail',
                    'detail' => 'monitoring loop has not started — see TROUBLESHOOTING.md "Monitor stuck on Pending"',
                ];
            }

            $dispatchAge = (int) Carbon::parse($dispatchLast)->diffInSeconds(now());
            $resultsAge = (int) Carbon::parse($resultsLast)->diffInSeconds(now());

            if ($dispatchAge > self::STALE_SECONDS || $resultsAge > self::STALE_SECONDS) {
                return [
                    'status' => 'fail',
                    'detail' => "stalled (dispatch {$dispatchAge}s ago, results {$resultsAge}s ago)",
                ];
            }

            return [
                'status' => 'ok',
                'detail' => "dispatch {$dispatchAge}s ago, results {$resultsAge}s ago",
            ];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => 'check failed: '.$e->getMessage()];
        }
    }

    /**
     * @return array{status: string, detail: string}
     */
    private function checkProbes(): array
    {
        try {
            $total = ProbeNode::count();
            $active = ProbeNode::activeCount();

            if ($total === 0) {
                return ['status' => 'fail', 'detail' => 'no probes have reported yet'];
            }

            if ($active === 0) {
                return ['status' => 'fail', 'detail' => "{$total} registered but all stale"];
            }

            return ['status' => 'ok', 'detail' => "{$active} active of {$total} registered"];
        } catch (\Throwable $e) {
            return ['status' => 'fail', 'detail' => 'check failed: '.$e->getMessage()];
        }
    }

    private function prefersJson(Request $request): bool
    {
        // Explicit JSON request — Accept header or .json in URL
        if ($request->expectsJson()) {
            return true;
        }

        // Default to HTML for browsers (Accept: text/html)
        return ! $request->acceptsHtml();
    }

    private function safeCount(\Closure $fn): ?int
    {
        try {
            return (int) $fn();
        } catch (\Throwable) {
            return null;
        }
    }
}
