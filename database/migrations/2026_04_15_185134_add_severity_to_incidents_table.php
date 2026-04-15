<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('severity', 16)->default('down')->after('monitor_id');
            $table->json('affected_node_ids')->nullable()->after('trigger_node_id');
            $table->index(['monitor_id', 'severity', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['monitor_id', 'severity', 'ended_at']);
            $table->dropColumn(['severity', 'affected_node_ids']);
        });
    }
};
