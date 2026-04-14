<?php

declare(strict_types=1);

beforeEach(function () {
    config(['app.jwt_secret' => 'test-secret-for-probe-token-generation']);

    $this->envPath = base_path('.env');
    $this->envBackup = file_exists($this->envPath) ? file_get_contents($this->envPath) : null;
});

afterEach(function () {
    if ($this->envBackup !== null) {
        file_put_contents($this->envPath, $this->envBackup);
    }
});

it('does not touch .env when generating a remote probe token', function () {
    $before = $this->envBackup ?? '';

    $this->artisan('probe:generate-token', [
        '--node-id' => 'us-east-1',
        '--expires' => 365,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $after = file_exists($this->envPath) ? file_get_contents($this->envPath) : '';
    expect($after)->toBe($before);
});

it('writes PROBE_NODE_ID and PROBE_JWT_TOKEN to .env when --local is passed', function () {
    file_put_contents($this->envPath, "PROBE_NODE_ID=old-id\nPROBE_JWT_TOKEN=old-token\n");

    $this->artisan('probe:generate-token', [
        '--node-id' => 'local-node-1',
        '--expires' => 365,
        '--local' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    $contents = file_get_contents($this->envPath);
    expect($contents)->toContain('PROBE_NODE_ID=local-node-1');
    expect($contents)->not->toContain('old-token');
});
