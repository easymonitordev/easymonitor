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
 * Starts the monitoring loops (DispatchMonitorChecks and ProcessMonitorResults)
 * every time the Horizon master process boots. Starting a loop retires any
 * chain left over from before, so restarting Horizon is always a clean reset:
 * php artisan horizon:terminate
 */
class MonitoringServiceProvider extends ServiceProvider
{
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

        // Only the Horizon master process starts the loops. Its supervisors
        // and workers (horizon:supervisor, horizon:work) and other horizon:*
        // commands such as the scheduled horizon:snapshot must not, or every
        // worker spawn would restart the loops.
        $argv = $_SERVER['argv'] ?? [];
        $command = $argv[1] ?? '';
        if ($command !== 'horizon') {
            return;
        }

        // Defer to booted() so DB/Redis/Queue are ready.
        $this->app->booted(function (Application $app): void {
            $this->bootstrapMonitoringLoop();
        });
    }

    /**
     * Start a fresh chain of each self-requeuing job, retiring older chains
     */
    private function bootstrapMonitoringLoop(): void
    {
        try {
            $lock = Cache::lock('monitoring:dispatcher:bootstrap', 10);

            if (! $lock->get()) {
                return;
            }

            try {
                DispatchMonitorChecks::startLoop();
                ProcessMonitorResults::startLoop();

                Log::info('Monitoring dispatcher and result processor bootstrapped');
            } finally {
                $lock->release();
            }
        } catch (\Throwable $e) {
            Log::warning('Monitoring bootstrap failed: '.$e->getMessage());
        }
    }
}
