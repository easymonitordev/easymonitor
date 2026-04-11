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
        ->assertSee('200')
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
