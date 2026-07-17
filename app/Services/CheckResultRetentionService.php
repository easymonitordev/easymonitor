<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manages the TimescaleDB retention and compression policies for the
 * check_results hypertable.
 *
 * Policies are idempotent: existing policies are removed before the new
 * ones are added, so this service can safely re-apply changed intervals.
 */
class CheckResultRetentionService
{
    /**
     * Whether the current database supports Timescale policies
     */
    public function isSupported(): bool
    {
        if (config('database.default') !== 'pgsql') {
            return false;
        }

        return DB::table('pg_extension')->where('extname', 'timescaledb')->exists();
    }

    /**
     * Apply compression and retention policies to check_results
     *
     * @return bool False when the database does not support Timescale policies
     */
    public function apply(?int $retentionDays = null, ?int $compressAfterDays = null): bool
    {
        $retentionDays ??= (int) config('easymonitor.retention.days');
        $compressAfterDays ??= (int) config('easymonitor.retention.compress_after_days');

        $statements = $this->statements($retentionDays, $compressAfterDays);

        if (! $this->isSupported()) {
            return false;
        }

        foreach ($statements as $statement) {
            DB::statement($statement);
        }

        return true;
    }

    /**
     * Remove the retention and compression policies from check_results
     */
    public function remove(): bool
    {
        if (! $this->isSupported()) {
            return false;
        }

        DB::statement("SELECT remove_retention_policy('check_results', if_exists => true)");
        DB::statement("SELECT remove_compression_policy('check_results', if_exists => true)");

        return true;
    }

    /**
     * The SQL statements that configure compression and retention
     *
     * @return list<string>
     */
    public function statements(int $retentionDays, int $compressAfterDays): array
    {
        if ($retentionDays < 1 || $compressAfterDays < 1) {
            throw new InvalidArgumentException('Retention and compression intervals must be at least 1 day.');
        }

        if ($retentionDays <= $compressAfterDays) {
            throw new InvalidArgumentException('Retention must be longer than the compression delay.');
        }

        return [
            "ALTER TABLE check_results SET (timescaledb.compress, timescaledb.compress_segmentby = 'monitor_id', timescaledb.compress_orderby = 'created_at DESC')",
            "SELECT remove_compression_policy('check_results', if_exists => true)",
            "SELECT add_compression_policy('check_results', INTERVAL '{$compressAfterDays} days')",
            "SELECT remove_retention_policy('check_results', if_exists => true)",
            "SELECT add_retention_policy('check_results', INTERVAL '{$retentionDays} days')",
        ];
    }
}
