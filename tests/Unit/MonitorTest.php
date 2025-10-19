<?php

use App\Models\Monitor;
use App\Models\Team;

test('monitor belongs to a team', function () {
    $team = Team::factory()->create();
    $monitor = Monitor::factory()->create(['team_id' => $team->id]);

    expect($monitor->team)->toBeInstanceOf(Team::class);
    expect($monitor->team->id)->toBe($team->id);
});

test('monitor can check if it is up', function () {
    $monitor = Monitor::factory()->up()->create();

    expect($monitor->isUp())->toBeTrue();
    expect($monitor->isDown())->toBeFalse();
    expect($monitor->isPending())->toBeFalse();
});

test('monitor can check if it is down', function () {
    $monitor = Monitor::factory()->down()->create();

    expect($monitor->isDown())->toBeTrue();
    expect($monitor->isUp())->toBeFalse();
    expect($monitor->isPending())->toBeFalse();
});

test('monitor can check if it is pending', function () {
    $monitor = Monitor::factory()->create(['status' => 'pending']);

    expect($monitor->isPending())->toBeTrue();
    expect($monitor->isUp())->toBeFalse();
    expect($monitor->isDown())->toBeFalse();
});

test('monitor has correct default values', function () {
    $monitor = Monitor::factory()->create();

    expect($monitor->is_active)->toBeTrue();
    expect($monitor->status)->toBe('pending');
    expect($monitor->check_interval)->toBe(60);
    expect($monitor->last_checked_at)->toBeNull();
    expect($monitor->last_error)->toBeNull();
});

test('monitor can be inactive', function () {
    $monitor = Monitor::factory()->inactive()->create();

    expect($monitor->is_active)->toBeFalse();
});
