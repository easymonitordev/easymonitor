<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Timeline updates on an incident (e.g. "Investigating" → "Identified" → "Resolved").
     */
    public function up(): void
    {
        Schema::create('status_page_incident_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('status_page_incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status_at_update', 30);
            $table->text('body');
            $table->timestamps();

            $table->index(['incident_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_page_incident_updates');
    }
};
