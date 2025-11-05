<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\MonitoringEngine\DispatchMonitorChecks;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Monitoring Service Provider
 *
 * Automatically starts the monitoring system by dispatching the initial
 * DispatchMonitorChecks job when Horizon/queue workers boot.
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
     * Bootstrap services
     */
    public function boot(): void
    {
        // Only run in console contexts (Horizon, queue workers, artisan)
        // Skip during unit tests
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            return;
        }

        // Use a Redis lock to prevent race conditions
        $lock = Cache::lock('monitoring:dispatcher:bootstrap', 10);

        try {
            if ($lock->get()) {
                // Check if already initialized
                if (Cache::has(self::INIT_KEY)) {
                    return;
                }

                // Dispatch the initial job to start the monitoring loop
                dispatch(new DispatchMonitorChecks);

                // Mark as initialized forever (manual restart required)
                Cache::forever(self::INIT_KEY, true);

                Log::info('Monitoring dispatcher bootstrapped');
            }
        } finally {
            $lock->release();
        }
    }
}
