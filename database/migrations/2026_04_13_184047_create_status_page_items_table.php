<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Items on a status page. Either a project (live-link: all its monitors appear)
     * or a single monitor. Sort order controls rendering.
     */
    public function up(): void
    {
        Schema::create('status_page_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_page_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // 'project' | 'monitor'
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('monitor_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label')->nullable(); // Optional override label (e.g. "Main API")
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['status_page_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_page_items');
    }
};
