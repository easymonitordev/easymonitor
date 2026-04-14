<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Registers each probe that reports results back to the server. Rows are
     * upserted automatically when a result arrives from a new node_id.
     * Quorum logic uses the "active" probe count as N for the majority rule.
     */
    public function up(): void
    {
        Schema::create('probe_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_id', 100)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'last_seen_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('probe_nodes');
    }
};
