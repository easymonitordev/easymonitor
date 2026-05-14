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
        Schema::table('notification_channels', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'type']);
            $table->string('label', 100)->nullable()->after('type');
            $table->index(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_channels', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'type']);
            $table->dropColumn('label');
            $table->unique(['user_id', 'type']);
        });
    }
};
