<?php

use App\Services\CheckResultRetentionService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * No-op on databases without TimescaleDB (e.g. the SQLite test database),
     * matching the guard used when the hypertable was created.
     */
    public function up(): void
    {
        app(CheckResultRetentionService::class)->apply();
    }

    /**
     * Reverse the migrations.
     *
     * Removes the policies only; already-compressed chunks stay compressed
     * because decompressing them could require more disk than is available.
     */
    public function down(): void
    {
        app(CheckResultRetentionService::class)->remove();
    }
};
