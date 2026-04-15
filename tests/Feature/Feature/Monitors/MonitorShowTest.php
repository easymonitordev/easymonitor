<?php

use App\Livewire\Monitors\Show;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('guests cannot access monitor show page', function () {
    $monitor = Monitor::factory()->create();

    $this->get(route('monitors.show', $monitor))->assertRedirect(route('login'));
});

test('user can view their own monitor', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->up()->create([
        'user_id' => $user->id,
        'name' => 'My Website',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['monitor' => $monitor])
        ->assertSuccessful()
        ->assertSee('My Website')
        ->assertSee($monitor->url);
});

test('user cannot view another users monitor', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['monitor' => $monitor])
        ->assertForbidden();
});

test('team member can view team monitor', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create(['owner_id' => $owner->id]);
    $team->users()->attach($member->id, ['role' => 'member']);
    $monitor = Monitor::factory()->forTeam($team)->create();

    $this->actingAs($member);

    Livewire::test(Show::class, ['monitor' => $monitor])
        ->assertSuccessful()
        ->assertSee($monitor->name);
});

test('monitor show page displays check results', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->up()->create(['user_id' => $user->id]);

    CheckResult::factory()->count(3)->create([
        'monitor_id' => $monitor->id,
        'is_up' => true,
        'response_time_ms' => 150,
        'status_code' => 200,
        'node_id' => 'local-node-1',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['monitor' => $monitor])
        ->assertSuccessful()
        ->assertSee('150ms')
        ->assertSee('local-node-1');
});

test('monitor show page displays uptime percentage', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->up()->create(['user_id' => $user->id]);

    CheckResult::factory()->count(9)->create([
        'monitor_id' => $monitor->id,
        'is_up' => true,
    ]);

    CheckResult::factory()->down()->create([
        'monitor_id' => $monitor->id,
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['monitor' => $monitor])
        ->assertSuccessful()
        ->assertSee('90%'); // 9/10 = 90%
});

test('monitor show page can change period', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->up()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['monitor' => $monitor])
        ->assertSuccessful()
        ->set('period', '7d')
        ->assertSuccessful()
        ->assertSee('7 Days');
});

test('monitor show page displays empty state when no results', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['monitor' => $monitor])
        ->assertSuccessful()
        ->assertSee('No check results yet');
});

test('monitor show page displays incidents with reason and duration', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->create(['user_id' => $user->id]);

    \App\Models\Incident::factory()->resolved()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subHours(2),
        'error_message' => 'Connection refused',
        'status_code' => 503,
        'trigger_node_id' => 'eu-central-1',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['monitor' => $monitor])
        ->assertSuccessful()
        ->assertSee('Incidents')
        ->assertSee('Connection refused')
        ->assertSee('503')
        ->assertSee('eu-central-1')
        ->assertSee('Resolved');
});

test('incidents section shows healthy message when there are none', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->up()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['monitor' => $monitor])
        ->assertSuccessful()
        ->assertSee('No incidents in this period');
});

test('response time by node section aggregates per-node stats', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->up()->create(['user_id' => $user->id]);

    CheckResult::factory()->count(3)->create([
        'monitor_id' => $monitor->id,
        'node_id' => 'eu-central-1',
        'is_up' => true,
        'response_time_ms' => 200,
    ]);

    CheckResult::factory()->count(2)->create([
        'monitor_id' => $monitor->id,
        'node_id' => 'us-east-1',
        'is_up' => true,
        'response_time_ms' => 500,
    ]);

    CheckResult::factory()->down()->create([
        'monitor_id' => $monitor->id,
        'node_id' => 'us-east-1',
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Show::class, ['monitor' => $monitor])
        ->assertSuccessful()
        ->assertSee('Response Time by Node')
        ->assertSee('eu-central-1')
        ->assertSee('us-east-1');

    $stats = $component->viewData('nodeStats');
    expect($stats)->toHaveCount(2);

    $eu = $stats->firstWhere('node_id', 'eu-central-1');
    expect($eu['min'])->toBe(200)
        ->and($eu['avg'])->toBe(200)
        ->and($eu['max'])->toBe(200)
        ->and($eu['total'])->toBe(3)
        ->and($eu['failures'])->toBe(0)
        ->and($eu['trend'])->toHaveCount(40);

    $us = $stats->firstWhere('node_id', 'us-east-1');
    expect($us['total'])->toBe(3)
        ->and($us['failures'])->toBe(1)
        ->and($us['avg'])->toBe(500);
});
