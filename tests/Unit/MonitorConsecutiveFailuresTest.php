<?php

use App\Models\Monitor;

test('monitor consecutive failures defaults to zero', function () {
    $monitor = Monitor::factory()->create();

    expect($monitor->consecutive_failures)->toBe(0);
    expect($monitor->failure_threshold)->toBe(1);
});

test('monitor stays pending until failure threshold is reached', function () {
    $monitor = Monitor::factory()->create([
        'status' => 'pending',
        'consecutive_failures' => 0,
        'failure_threshold' => 3,
    ]);

    // Simulate 2 failures (below threshold)
    $monitor->update([
        'consecutive_failures' => 2,
    ]);

    // Status should still be pending since threshold not reached
    expect($monitor->fresh()->status)->toBe('pending');
    expect($monitor->fresh()->consecutive_failures)->toBe(2);
});

test('monitor failures reset to zero when check succeeds', function () {
    $monitor = Monitor::factory()->create([
        'status' => 'up',
        'consecutive_failures' => 2,
    ]);

    $monitor->update([
        'consecutive_failures' => 0,
        'status' => 'up',
    ]);

    expect($monitor->fresh()->consecutive_failures)->toBe(0);
    expect($monitor->fresh()->status)->toBe('up');
});

test('monitor has custom failure threshold', function () {
    $monitor = Monitor::factory()->create([
        'failure_threshold' => 5,
    ]);

    expect($monitor->failure_threshold)->toBe(5);
});
