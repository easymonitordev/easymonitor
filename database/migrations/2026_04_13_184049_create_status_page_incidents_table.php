<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Incidents and scheduled maintenance announcements on a status page.
     */
    public function up(): void
    {
        Schema::create('status_page_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // author
            $table->string('title');
            $table->text('body')->nullable();

            // Type + status
            $table->string('type', 20)->default('incident'); // incident | maintenance
            $table->string('status', 30)->default('investigating');
            // incident: investigating | identified | monitoring | resolved
            // maintenance: scheduled | in_progress | completed

            // Severity (incident only)
            $table->string('severity', 20)->nullable(); // minor | major | critical

            // Affected monitors (JSON array of monitor IDs)
            $table->json('affected_monitor_ids')->nullable();

            // Scheduling (maintenance)
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('scheduled_until')->nullable();

            // Lifecycle
            $table->timestamp('started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->index(['status_page_id', 'created_at']);
            $table->index(['status_page_id', 'resolved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_page_incidents');
    }
};
