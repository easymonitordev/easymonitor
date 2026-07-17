<?php

use App\Livewire\Dashboard;
use App\Models\CheckResult;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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

test('instance owner sees the update banner when a newer release is cached', function () {
    config()->set('easymonitor.version', '0.1.5');
    Cache::put('easymonitor:latest-release', [
        'version' => '0.1.6',
        'url' => 'https://github.com/easymonitordev/easymonitor/releases/tag/v0.1.6',
        'published_at' => '2026-07-17T00:00:00Z',
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('EasyMonitor v0.1.6 is available')
        ->assertSee('Release notes');
});

test('non-owner users do not see the update banner', function () {
    config()->set('easymonitor.version', '0.1.5');
    Cache::put('easymonitor:latest-release', ['version' => '0.1.6', 'url' => null, 'published_at' => null]);

    User::factory()->create();
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertDontSee('is available');
});

test('no update banner appears when the app is up to date', function () {
    config()->set('easymonitor.version', '0.1.5');
    Cache::put('easymonitor:latest-release', ['version' => '0.1.5', 'url' => null, 'published_at' => null]);

    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertDontSee('is available');
});

test('dashboard shows an engine health banner when the dispatch loop stalls', function () {
    Cache::put('monitor:dispatch-checks:last-run', now()->subSeconds(600), 3600);
    Cache::put('monitor:process-results:last-run', now()->subSeconds(10), 3600);

    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Monitoring engine unhealthy')
        ->assertSee('checks are not being dispatched');
});

test('no engine health banner appears when heartbeats are fresh or absent', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertDontSee('Monitoring engine unhealthy');
});
