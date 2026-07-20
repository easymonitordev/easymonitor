<?php

use App\Services\CheckResultRollupService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Continuous aggregates cannot be created inside a transaction block.
     */
    public $withinTransaction = false;

    /**
     * Run the migrations.
     *
     * Creates the hourly rollup over check_results and backfills it from
     * all existing raw data, so long-period charts have history from day
     * one. No-op on databases without TimescaleDB (e.g. the SQLite test
     * database), matching the other Timescale migrations.
     */
    public function up(): void
    {
        app(CheckResultRollupService::class)->create();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(CheckResultRollupService::class)->drop();
    }
};
