<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\UpdateChecker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily job that refreshes the cached latest-release information
 *
 * The dashboard shows an update banner to the instance owner when the
 * cached release is newer than the running version.
 */
class CheckForUpdates implements ShouldQueue
{
    use Queueable;

    /**
     * The number of seconds the job can run before timing out
     */
    public int $timeout = 30;

    /**
     * Execute the job.
     */
    public function handle(UpdateChecker $updateChecker): void
    {
        $updateChecker->check();
    }
}
