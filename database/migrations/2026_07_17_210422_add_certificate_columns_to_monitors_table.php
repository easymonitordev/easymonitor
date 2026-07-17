<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->timestamp('cert_expires_at')->nullable();
            $table->string('cert_issuer')->nullable();
            $table->smallInteger('cert_alerted_threshold_days')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table) {
            $table->dropColumn(['cert_expires_at', 'cert_issuer', 'cert_alerted_threshold_days']);
        });
    }
};
