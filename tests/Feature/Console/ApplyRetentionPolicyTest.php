<?php

declare(strict_types=1);

it('skips gracefully when the database does not support Timescale policies', function () {
    $this->artisan('easymonitor:retention')
        ->expectsOutputToContain('Skipped: retention policies require PostgreSQL with the TimescaleDB extension.')
        ->assertSuccessful();
});

it('fails with a clear message for invalid intervals', function () {
    $this->artisan('easymonitor:retention', ['--days' => 0])
        ->expectsOutputToContain('Retention and compression intervals must be at least 1 day.')
        ->assertFailed();
});

it('fails when retention is not longer than the compression delay', function () {
    $this->artisan('easymonitor:retention', ['--days' => 7, '--compress-after' => 7])
        ->expectsOutputToContain('Retention must be longer than the compression delay.')
        ->assertFailed();
});

it('uses configured defaults for the intervals', function () {
    config()->set('easymonitor.retention.days', 3);
    config()->set('easymonitor.retention.compress_after_days', 7);

    $this->artisan('easymonitor:retention')
        ->expectsOutputToContain('Retention must be longer than the compression delay.')
        ->assertFailed();
});
