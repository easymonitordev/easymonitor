<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('error_message')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->string('trigger_node_id')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'started_at']);
            $table->index(['monitor_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
