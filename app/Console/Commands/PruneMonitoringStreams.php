<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MonitoringEngine\StreamMaintenance;
use Illuminate\Console\Command;

/**
 * Trims the monitoring Redis Streams and removes idle result consumers
 *
 * Runs hourly via the scheduler so Redis memory stays bounded.
 */
class PruneMonitoringStreams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'easymonitor:prune-streams';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trim the monitoring Redis Streams and remove idle result consumers';

    /**
     * Execute the console command
     */
    public function handle(StreamMaintenance $maintenance): int
    {
        foreach ($maintenance->trimStreams() as $stream => $removed) {
            $this->components->info("Trimmed {$removed} entries from the \"{$stream}\" stream.");
        }

        $deleted = $maintenance->pruneIdleResultConsumers();
        $this->components->info("Removed {$deleted} idle result consumer(s).");

        return self::SUCCESS;
    }
}
