<?php

declare(strict_types=1);

use App\Services\CheckResultRollupService;

it('creates an hourly continuous aggregate with weighted-average building blocks', function () {
    $sql = implode(' ', (new CheckResultRollupService)->createStatements());

    expect($sql)
        ->toContain('CREATE MATERIALIZED VIEW check_results_hourly')
        ->toContain('timescaledb.continuous')
        ->toContain("time_bucket(INTERVAL '1 hour', created_at)")
        ->toContain('SUM(response_time_ms) FILTER (WHERE is_up) AS response_time_sum_ms')
        ->toContain('GROUP BY monitor_id, node_id, bucket')
        ->toContain('timescaledb.materialized_only = false')
        ->toContain("CALL refresh_continuous_aggregate('check_results_hourly', NULL, (now() - INTERVAL '1 hour')::timestamp)");
});

it('generates refresh and retention policies with the given rollup window', function () {
    $sql = implode(' ', (new CheckResultRollupService)->policyStatements(730));

    expect($sql)
        ->toContain("add_continuous_aggregate_policy('check_results_hourly'")
        ->toContain("schedule_interval => INTERVAL '30 minutes'")
        ->toContain("add_retention_policy('check_results_hourly', INTERVAL '730 days')");
});

it('removes existing policies before adding new ones so re-applying is idempotent', function () {
    $statements = (new CheckResultRollupService)->policyStatements(365);

    $removeRefresh = array_search("SELECT remove_continuous_aggregate_policy('check_results_hourly', if_exists => true)", $statements);
    $removeRetention = array_search("SELECT remove_retention_policy('check_results_hourly', if_exists => true)", $statements);

    expect($removeRefresh)->not->toBeFalse()
        ->and($removeRetention)->not->toBeFalse()
        ->and($removeRefresh)->toBeLessThan((int) array_search("SELECT add_retention_policy('check_results_hourly', INTERVAL '365 days')", $statements));
});

it('rejects a non-positive rollup retention window', function () {
    (new CheckResultRollupService)->policyStatements(0);
})->throws(InvalidArgumentException::class, 'Rollup retention must be at least 1 day.');

it('reports unsupported and not queryable on the SQLite test database', function () {
    $service = new CheckResultRollupService;

    expect($service->isSupported())->toBeFalse()
        ->and($service->isQueryable())->toBeFalse();
});

it('skips create, policy application, and drop on unsupported databases', function () {
    $service = new CheckResultRollupService;

    expect($service->create())->toBeFalse()
        ->and($service->applyPolicies(730))->toBeFalse()
        ->and($service->drop())->toBeFalse();
});

it('validates the interval even when the database is unsupported', function () {
    (new CheckResultRollupService)->applyPolicies(-1);
})->throws(InvalidArgumentException::class);
