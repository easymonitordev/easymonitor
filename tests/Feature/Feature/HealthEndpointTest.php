<?php

use App\Models\Monitor;
use App\Models\ProbeNode;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    // Fake a healthy monitoring loop so the "stalled" check passes by default.
    Cache::put('monitor:dispatch-checks:last-run', now(), 300);
    Cache::put('monitor:process-results:last-run', now(), 300);

    // The test env has no real Redis server, so stub the ping.
    Redis::shouldReceive('connection')->andReturnSelf();
    Redis::shouldReceive('ping')->andReturn('PONG');
});

test('healthz is accessible without auth', function () {
    ProbeNode::factory()->create();

    $this->assertGuest();
    $this->getJson('/healthz')->assertStatus(200);
});

test('healthz returns json when requested', function () {
    ProbeNode::factory()->create();

    $response = $this->getJson('/healthz');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'timestamp',
            'components' => ['database', 'redis', 'monitoring_loop', 'probes'],
            'stats' => ['active_monitors', 'active_probes', 'total_monitors'],
        ]);
});

test('healthz returns 200 when all components healthy', function () {
    ProbeNode::factory()->create();
    Monitor::factory()->count(2)->create(['is_active' => true]);

    $response = $this->getJson('/healthz');

    $response->assertStatus(200)
        ->assertJson(['status' => 'ok'])
        ->assertJsonPath('components.database.status', 'ok')
        ->assertJsonPath('components.redis.status', 'ok')
        ->assertJsonPath('components.monitoring_loop.status', 'ok')
        ->assertJsonPath('components.probes.status', 'ok');
});

test('healthz returns 503 when monitoring loop has never run', function () {
    Cache::forget('monitor:dispatch-checks:last-run');
    Cache::forget('monitor:process-results:last-run');
    ProbeNode::factory()->create();

    $response = $this->getJson('/healthz');

    $response->assertStatus(503)
        ->assertJson(['status' => 'fail'])
        ->assertJsonPath('components.monitoring_loop.status', 'fail');
});

test('healthz returns 503 when monitoring loop is stale', function () {
    Cache::put('monitor:dispatch-checks:last-run', now()->subMinutes(5), 300);
    Cache::put('monitor:process-results:last-run', now()->subMinutes(5), 300);
    ProbeNode::factory()->create();

    $response = $this->getJson('/healthz');

    $response->assertStatus(503)
        ->assertJsonPath('components.monitoring_loop.status', 'fail');
});

test('healthz returns 503 when no probes registered', function () {
    $response = $this->getJson('/healthz');

    $response->assertStatus(503)
        ->assertJsonPath('components.probes.status', 'fail');
});

test('healthz returns 503 when all probes stale', function () {
    ProbeNode::factory()->stale()->create();

    $response = $this->getJson('/healthz');

    $response->assertStatus(503)
        ->assertJsonPath('components.probes.status', 'fail');
});

test('healthz returns html for browser requests', function () {
    ProbeNode::factory()->create();

    $response = $this->get('/healthz');

    $response->assertSuccessful()
        ->assertHeader('content-type', 'text/html; charset=UTF-8')
        ->assertSee('System Health')
        ->assertSee('All systems operational');
});

test('healthz html shows failure state', function () {
    Cache::forget('monitor:dispatch-checks:last-run');
    Cache::forget('monitor:process-results:last-run');

    $response = $this->get('/healthz');

    $response->assertStatus(503)
        ->assertSee('One or more components need attention')
        ->assertSee('monitoring loop has not started', false);
});

test('healthz stats include monitor and probe counts', function () {
    User::factory()->create();
    Monitor::factory()->count(3)->create(['is_active' => true]);
    Monitor::factory()->count(2)->create(['is_active' => false]);
    ProbeNode::factory()->count(2)->create();

    $response = $this->getJson('/healthz');

    $response->assertJsonPath('stats.active_monitors', 3)
        ->assertJsonPath('stats.total_monitors', 5)
        ->assertJsonPath('stats.active_probes', 2);
});
