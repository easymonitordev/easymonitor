<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Phase 2 of the alerting rework — cross-probe quorum.
     *
     * - check_results.round_id groups results from multiple probes for the
     *   same dispatched check, so the consumer can decide by majority.
     * - monitors.last_decided_round_id tracks which round the monitor's
     *   current status reflects, so we don't re-process the same round twice.
     */
    public function up(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->string('round_id', 64)->nullable()->after('node_id');
            $table->index(['monitor_id', 'round_id']);
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->string('last_decided_round_id', 64)->nullable()->after('consecutive_failures');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('check_results', function (Blueprint $table) {
            $table->dropIndex(['monitor_id', 'round_id']);
            $table->dropColumn('round_id');
        });

        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn('last_decided_round_id');
        });
    }
};
