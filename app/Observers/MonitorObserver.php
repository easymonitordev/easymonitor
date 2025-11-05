<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Monitor;
use App\Services\MonitoringEngine\CheckDispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Monitor Observer
 *
 * Automatically dispatches checks for newly created monitors
 * to ensure immediate first check instead of waiting for the next cycle.
 */
class MonitorObserver
{
    /**
     * Handle the Monitor "created" event
     */
    public function created(Monitor $monitor): void
    {
        // Only dispatch if the monitor is active
        if (! $monitor->is_active) {
            return;
        }

        try {
            $dispatcher = app(CheckDispatcher::class);
            $dispatcher->ensureConsumerGroupExists();
            $dispatcher->dispatchCheck($monitor);

            // Set next_run_at for the regular loop
            $monitor->update([
                'next_run_at' => now()->addSeconds($monitor->check_interval),
            ]);

            Log::info('Immediate check dispatched for new monitor', [
                'monitor_id' => $monitor->id,
                'monitor_name' => $monitor->name,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch immediate check for new monitor', [
                'monitor_id' => $monitor->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Monitor "updated" event
     */
    public function updated(Monitor $monitor): void
    {
        // If monitor was just activated, dispatch immediate check
        if ($monitor->wasChanged('is_active') && $monitor->is_active) {
            try {
                $dispatcher = app(CheckDispatcher::class);
                $dispatcher->ensureConsumerGroupExists();
                $dispatcher->dispatchCheck($monitor);

                // Set next_run_at
                $monitor->updateQuietly([
                    'next_run_at' => now()->addSeconds($monitor->check_interval),
                ]);

                Log::info('Immediate check dispatched for activated monitor', [
                    'monitor_id' => $monitor->id,
                    'monitor_name' => $monitor->name,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to dispatch immediate check for activated monitor', [
                    'monitor_id' => $monitor->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
