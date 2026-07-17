<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CheckResultRetentionService;
use App\Services\CheckResultRollupService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Apply the TimescaleDB retention and compression policies for check results
 *
 * Run this after changing CHECK_RESULT_RETENTION_DAYS or
 * CHECK_RESULT_COMPRESS_AFTER_DAYS so the database policies pick up the
 * new intervals.
 */
class ApplyRetentionPolicy extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'easymonitor:retention
                            {--days= : Keep raw check results for this many days (default: config value)}
                            {--compress-after= : Compress chunks older than this many days (default: config value)}
                            {--rollup-days= : Keep hourly rollup data for this many days (default: config value)}';

    /**
     * The console command description.
     */
    protected $description = 'Apply the check result retention and compression policies';

    /**
     * Execute the console command.
     */
    public function handle(CheckResultRetentionService $retention, CheckResultRollupService $rollup): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('easymonitor.retention.days');

        $compressAfter = $this->option('compress-after') !== null
            ? (int) $this->option('compress-after')
            : (int) config('easymonitor.retention.compress_after_days');

        $rollupDays = $this->option('rollup-days') !== null
            ? (int) $this->option('rollup-days')
            : (int) config('easymonitor.retention.rollup_days');

        try {
            $applied = $retention->apply($days, $compressAfter);

            if ($applied && $rollup->isQueryable()) {
                $rollup->applyPolicies($rollupDays);
            }
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $applied) {
            $this->components->warn('Skipped: retention policies require PostgreSQL with the TimescaleDB extension.');

            return self::SUCCESS;
        }

        $this->components->info("Retention policy applied: raw check results kept for {$days} days, compressed after {$compressAfter} days, hourly rollup kept for {$rollupDays} days.");

        return self::SUCCESS;
    }
}
