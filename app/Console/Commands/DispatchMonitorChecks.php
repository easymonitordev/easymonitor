<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MonitoringEngine\CheckDispatcher;
use Illuminate\Console\Command;

/**
 * Dispatches active monitor checks to Redis Streams for probe nodes
 *
 * This command runs every 30 seconds via the scheduler and publishes
 * check jobs to the "checks" stream for probe nodes to consume.
 */
class DispatchMonitorChecks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitors:dispatch
                            {--setup : Setup consumer group without dispatching}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch active monitor checks to Redis Streams for probe nodes to consume';

    /**
     * Execute the console command
     */
    public function handle(CheckDispatcher $dispatcher): int
    {
        // Ensure consumer group exists
        $dispatcher->ensureConsumerGroupExists();

        if ($this->option('setup')) {
            $this->info('Consumer group "probes" created successfully.');

            return self::SUCCESS;
        }

        // Dispatch all due checks
        $count = $dispatcher->dispatchDueChecks();

        if ($count > 0) {
            $this->info("Dispatched {$count} monitor check(s) to Redis Streams.");
        }

        // Show pending count
        $pending = $dispatcher->getPendingCheckCount();
        if ($pending > 0) {
            $this->comment("Pending checks in queue: {$pending}");
        }

        return self::SUCCESS;
    }
}
