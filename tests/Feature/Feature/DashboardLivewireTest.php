<?php

use App\Livewire\Dashboard;
use App\Models\CheckResult;
use App\Models\Monitor;
use App\Models\User;
use Livewire\Livewire;

test('dashboard displays monitoring stats', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->up()->create(['user_id' => $user->id]);

    CheckResult::factory()->count(5)->create([
        'monitor_id' => $monitor->id,
        'is_up' => true,
        'response_time_ms' => 100,
    ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee($monitor->name)
        ->assertSee('1') // total monitors
        ->assertSee('Up');
});

test('dashboard shows empty state when no monitors exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('No monitors yet');
});

test('dashboard shows monitors from all teams', function () {
    $user = User::factory()->create();
    $personalMonitor = Monitor::factory()->up()->create([
        'user_id' => $user->id,
        'name' => 'Personal Site',
    ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Personal Site');
});

test('dashboard shows recent incidents', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->down()->create([
        'user_id' => $user->id,
        'name' => 'Failing Site',
    ]);

    \App\Models\Incident::factory()->create([
        'monitor_id' => $monitor->id,
        'severity' => \App\Models\Incident::SEVERITY_DOWN,
        'started_at' => now()->subMinutes(3),
        'error_message' => 'Connection timeout',
    ]);

    $this->actingAs($user);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Connection timeout');
});

test('guests cannot access dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});
