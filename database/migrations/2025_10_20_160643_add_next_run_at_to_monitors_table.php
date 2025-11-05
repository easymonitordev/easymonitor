<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->timestamp('next_run_at')->nullable()->after('last_checked_at');
            $table->index('next_run_at');
        });

        // Set next_run_at for existing monitors to now
        DB::table('monitors')->whereNull('next_run_at')->update([
            'next_run_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropIndex(['next_run_at']);
            $table->dropColumn('next_run_at');
        });
    }
};
