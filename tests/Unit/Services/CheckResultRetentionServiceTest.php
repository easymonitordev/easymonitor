<?php

declare(strict_types=1);

use App\Services\CheckResultRetentionService;

it('generates compression and retention statements with the given intervals', function () {
    $statements = (new CheckResultRetentionService)->statements(90, 7);

    expect($statements)->toHaveCount(5)
        ->and(implode(' ', $statements))
        ->toContain("add_compression_policy('check_results', INTERVAL '7 days')")
        ->toContain("add_retention_policy('check_results', INTERVAL '90 days')")
        ->toContain("timescaledb.compress_segmentby = 'monitor_id'");
});

it('removes existing policies before adding new ones so re-applying is idempotent', function () {
    $statements = (new CheckResultRetentionService)->statements(30, 3);

    $removeCompression = array_search("SELECT remove_compression_policy('check_results', if_exists => true)", $statements);
    $addCompression = array_search("SELECT add_compression_policy('check_results', INTERVAL '3 days')", $statements);
    $removeRetention = array_search("SELECT remove_retention_policy('check_results', if_exists => true)", $statements);
    $addRetention = array_search("SELECT add_retention_policy('check_results', INTERVAL '30 days')", $statements);

    expect($removeCompression)->toBeLessThan($addCompression)
        ->and($removeRetention)->toBeLessThan($addRetention);
});

it('rejects non-positive intervals', function (int $retentionDays, int $compressAfterDays) {
    (new CheckResultRetentionService)->statements($retentionDays, $compressAfterDays);
})->throws(InvalidArgumentException::class)->with([
    'zero retention' => [0, 7],
    'negative retention' => [-5, 7],
    'zero compression delay' => [90, 0],
]);

it('rejects retention shorter than or equal to the compression delay', function () {
    (new CheckResultRetentionService)->statements(7, 7);
})->throws(InvalidArgumentException::class, 'Retention must be longer than the compression delay.');

it('reports Timescale as unsupported on the SQLite test database', function () {
    expect((new CheckResultRetentionService)->isSupported())->toBeFalse();
});

it('skips applying policies on unsupported databases without touching the schema', function () {
    expect((new CheckResultRetentionService)->apply())->toBeFalse()
        ->and((new CheckResultRetentionService)->remove())->toBeFalse();
});

it('reads default intervals from configuration when applying', function () {
    config()->set('easymonitor.retention.days', 5);
    config()->set('easymonitor.retention.compress_after_days', 7);

    (new CheckResultRetentionService)->apply();
})->throws(InvalidArgumentException::class);
