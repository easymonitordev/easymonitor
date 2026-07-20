<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Manages the hourly continuous aggregate over check_results.
 *
 * The aggregate (check_results_hourly) preserves per-monitor, per-node
 * hourly stats long after the raw rows are dropped by the retention
 * policy, so long-period charts keep working. Refresh and retention
 * policies are remove-then-add so re-applying changed intervals is safe.
 */
class CheckResultRollupService
{
    public const VIEW_NAME = 'check_results_hourly';

    /**
     * Whether the current database supports continuous aggregates
     */
    public function isSupported(): bool
    {
        if (config('database.default') !== 'pgsql') {
            return false;
        }

        return DB::table('pg_extension')->where('extname', 'timescaledb')->exists();
    }

    /**
     * Whether the aggregate exists and can be queried
     */
    public function isQueryable(): bool
    {
        if (! $this->isSupported()) {
            return false;
        }

        return DB::table('timescaledb_information.continuous_aggregates')
            ->where('view_name', self::VIEW_NAME)
            ->exists();
    }

    /**
     * Create the aggregate, backfill it, and apply its policies
     *
     * @return bool False when the database does not support continuous aggregates
     */
    public function create(): bool
    {
        if (! $this->isSupported()) {
            return false;
        }

        if (! $this->isQueryable()) {
            foreach ($this->createStatements() as $statement) {
                DB::statement($statement);
            }
        }

        $this->applyPolicies();

        return true;
    }

    /**
     * Apply (or re-apply) the refresh and retention policies
     *
     * @return bool False when the database does not support continuous aggregates
     */
    public function applyPolicies(?int $retentionDays = null): bool
    {
        $retentionDays ??= (int) config('easymonitor.retention.rollup_days');

        $statements = $this->policyStatements($retentionDays);

        if (! $this->isQueryable()) {
            return false;
        }

        foreach ($statements as $statement) {
            DB::statement($statement);
        }

        return true;
    }

    /**
     * Drop the aggregate and its policies
     */
    public function drop(): bool
    {
        if (! $this->isSupported()) {
            return false;
        }

        DB::statement('DROP MATERIALIZED VIEW IF EXISTS '.self::VIEW_NAME.' CASCADE');

        return true;
    }

    /**
     * The DDL that creates and backfills the aggregate
     *
     * Response-time stats keep the SUM (not AVG) so downstream queries can
     * compute correctly weighted averages across buckets.
     *
     * @return list<string>
     */
    public function createStatements(): array
    {
        $view = self::VIEW_NAME;

        return [
            "CREATE MATERIALIZED VIEW {$view}
                WITH (timescaledb.continuous) AS
                SELECT
                    monitor_id,
                    node_id,
                    time_bucket(INTERVAL '1 hour', created_at) AS bucket,
                    COUNT(*) AS total,
                    COUNT(*) FILTER (WHERE is_up) AS up_count,
                    COUNT(*) FILTER (WHERE NOT is_up) AS down_count,
                    SUM(response_time_ms) FILTER (WHERE is_up) AS response_time_sum_ms,
                    MIN(response_time_ms) FILTER (WHERE is_up) AS min_response_time_ms,
                    MAX(response_time_ms) FILTER (WHERE is_up) AS max_response_time_ms
                FROM check_results
                GROUP BY monitor_id, node_id, bucket
                WITH NO DATA",
            "ALTER MATERIALIZED VIEW {$view} SET (timescaledb.materialized_only = false)",
            "CALL refresh_continuous_aggregate('{$view}', NULL, (now() - INTERVAL '1 hour')::timestamp)",
        ];
    }

    /**
     * The refresh and retention policy statements
     *
     * @return list<string>
     */
    public function policyStatements(int $retentionDays): array
    {
        if ($retentionDays < 1) {
            throw new InvalidArgumentException('Rollup retention must be at least 1 day.');
        }

        $view = self::VIEW_NAME;

        return [
            "SELECT remove_continuous_aggregate_policy('{$view}', if_exists => true)",
            "SELECT add_continuous_aggregate_policy('{$view}',
                start_offset => INTERVAL '3 hours',
                end_offset => INTERVAL '1 hour',
                schedule_interval => INTERVAL '30 minutes')",
            "SELECT remove_retention_policy('{$view}', if_exists => true)",
            "SELECT add_retention_policy('{$view}', INTERVAL '{$retentionDays} days')",
        ];
    }
}
