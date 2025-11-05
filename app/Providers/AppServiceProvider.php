<?php

namespace App\Providers;

use App\Models\Monitor;
use App\Observers\MonitorObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Monitor::observe(MonitorObserver::class);
    }
}
