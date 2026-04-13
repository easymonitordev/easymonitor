<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 1 of the alerting rework — single-probe users get instant alerts by
     * default. Existing rows with the old default of 3 are reset to 1, since
     * the threshold was effectively a workaround for the missing multi-probe
     * quorum logic. Users with intentionally-noisy endpoints can bump it back
     * up via the Advanced section in the monitor edit form.
     *
     * The DB column default is left at 3 (unchanged) — application code now
     * always sets the value explicitly, so the DB default is no longer relied on.
     */
    public function up(): void
    {
        DB::table('monitors')
            ->where('failure_threshold', 3)
            ->update(['failure_threshold' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('monitors')
            ->where('failure_threshold', 1)
            ->update(['failure_threshold' => 3]);
    }
};
