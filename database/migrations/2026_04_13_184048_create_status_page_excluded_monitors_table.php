<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * When a project is added to a status page as a live link, individual
     * monitors inside that project can be hidden per-page via this table.
     */
    public function up(): void
    {
        Schema::create('status_page_excluded_monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['status_page_id', 'monitor_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_page_excluded_monitors');
    }
};
