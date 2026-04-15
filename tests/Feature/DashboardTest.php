<?php

use App\Livewire\Dashboard;
use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertStatus(200);
});

test('dashboard does not list single-probe failures as incidents', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->up()->create(['user_id' => $user->id]);

    // A handful of raw failed check results (single-probe noise, no quorum).
    CheckResult::factory()->count(3)->down()->create([
        'monitor_id' => $monitor->id,
        'error_message' => 'single node blip',
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Dashboard::class)->assertSuccessful();

    expect($component->viewData('recentIncidents'))->toHaveCount(0);
    expect($component->viewData('downIncidentsCount'))->toBe(0);
});

test('dashboard surfaces degraded incidents with warning styling', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->up()->create(['user_id' => $user->id, 'name' => 'Blog']);

    Incident::factory()->degraded()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(10),
        'affected_node_ids' => ['eu-central-1'],
        'error_message' => 'timeout from one probe',
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Degraded')
        ->assertSee('eu-central-1')
        ->assertSee('1 monitor is degraded');

    expect($component->viewData('downIncidentsCount'))->toBe(0);
    expect($component->viewData('degradedIncidentsCount'))->toBe(1);
    expect($component->viewData('monitorsDegraded'))->toBe(1);
});

test('dashboard shows down incidents with error styling', function () {
    $user = User::factory()->create();
    $monitor = Monitor::factory()->up()->create(['user_id' => $user->id, 'name' => 'API']);

    Incident::factory()->create([
        'monitor_id' => $monitor->id,
        'started_at' => now()->subMinutes(5),
        'severity' => Incident::SEVERITY_DOWN,
        'affected_node_ids' => ['eu-central-1', 'us-east-1'],
        'error_message' => 'context deadline exceeded',
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('context deadline exceeded')
        ->assertSee('eu-central-1')
        ->assertSee('us-east-1');

    expect($component->viewData('downIncidentsCount'))->toBe(1);
});
