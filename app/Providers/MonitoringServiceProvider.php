<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\MonitoringEngine\DispatchMonitorChecks;
use App\Jobs\MonitoringEngine\ProcessMonitorResults;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Monitoring Service Provider
 *
 * Automatically starts the monitoring system by dispatching the initial
 * DispatchMonitorChecks and ProcessMonitorResults jobs when Horizon/queue workers boot.
 *
 * To restart the monitoring loop, run:
 * php artisan cache:forget monitoring:dispatcher:initialized
 */
class MonitoringServiceProvider extends ServiceProvider
{
    /**
     * The cache key for the initialization flag
     */
    private const INIT_KEY = 'monitoring:dispatcher:initialized';

    /**
     * Bootstrap services.
     *
     * The actual dispatch runs *after* all other providers have booted,
     * via the `booted()` callback. Trying to dispatch directly from boot()
     * can touch services (DB, Redis, queue) that aren't ready yet.
     */
    public function boot(): void
    {
        // Only run in CLI contexts (Horizon, queue workers, artisan). Skip HTTP
        // requests (so web traffic doesn't keep triggering this) and unit tests.
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            return;
        }

        // Skip trivial artisan invocations that shouldn't kick off the loop.
        // Only the `horizon` and `horizon:work*` commands should bootstrap.
        $argv = $_SERVER['argv'] ?? [];
        $command = $argv[1] ?? '';
        if (! str_starts_with($command, 'horizon')) {
            return;
        }

        // Defer to booted() so DB/Redis/Queue are ready.
        $this->app->booted(function (Application $app): void {
            $this->bootstrapMonitoringLoop();
        });
    }

    /**
     * Dispatch the initial self-requeuing jobs, guarded by a cache flag so
     * we only bootstrap once per Horizon lifecycle.
     */
    private function bootstrapMonitoringLoop(): void
    {
        try {
            $lock = Cache::lock('monitoring:dispatcher:bootstrap', 10);

            if (! $lock->get()) {
                return;
            }

            try {
                if (Cache::has(self::INIT_KEY)) {
                    return;
                }

                dispatch(new DispatchMonitorChecks);
                dispatch(new ProcessMonitorResults);

                Cache::forever(self::INIT_KEY, true);

                Log::info('Monitoring dispatcher and result processor bootstrapped');
            } finally {
                $lock->release();
            }
        } catch (\Throwable $e) {
            Log::warning('Monitoring bootstrap failed: '.$e->getMessage());
        }
    }
}
