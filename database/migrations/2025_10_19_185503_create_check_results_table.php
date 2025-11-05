<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isPgsql = config('database.default') === 'pgsql';

        Schema::create('check_results', function (Blueprint $table) use ($isPgsql) {
            if ($isPgsql) {
                // PostgreSQL: Use bigint without auto-increment for composite PK
                $table->bigInteger('id');
            } else {
                // SQLite/other: Standard auto-increment
                $table->id();
            }

            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->string('node_id', 50)->index(); // Probe node identifier (e.g., 'ams-1', 'nyc-1')
            $table->boolean('is_up'); // true = up, false = down
            $table->integer('response_time_ms')->nullable(); // Latency in milliseconds
            $table->smallInteger('status_code')->nullable(); // HTTP status code
            $table->text('error_message')->nullable(); // Error details if check failed
            $table->timestamps();

            if ($isPgsql) {
                // Composite primary key including created_at for TimescaleDB
                $table->primary(['id', 'created_at']);
            }

            // Index for efficient queries
            $table->index(['monitor_id', 'created_at']);
            $table->index(['node_id', 'created_at']);
        });

        // Add sequence for id generation in PostgreSQL
        if ($isPgsql) {
            DB::statement('CREATE SEQUENCE IF NOT EXISTS check_results_id_seq');
            DB::statement("ALTER TABLE check_results ALTER COLUMN id SET DEFAULT nextval('check_results_id_seq')");
        }

        // Convert to TimescaleDB hypertable for efficient time-series queries
        if ($isPgsql) {
            DB::statement("SELECT create_hypertable('check_results', 'created_at', if_not_exists => TRUE)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('check_results');
    }
};
