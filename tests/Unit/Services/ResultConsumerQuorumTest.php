<?php

use App\Models\Monitor;
use App\Models\ProbeNode;
use App\Models\User;
use App\Notifications\MonitorDown;
use App\Notifications\MonitorRecovered;
use App\Services\MonitoringEngine\ResultConsumer;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

function makeMonitor(array $overrides = []): Monitor
{
    $user = User::factory()->create();

    return Monitor::factory()->create(array_merge([
        'user_id' => $user->id,
        'status' => 'up',
        'consecutive_failures' => 0,
        'failure_threshold' => 1,
    ], $overrides));
}

function resultFields(int $monitorId, string $nodeId, string $roundId, bool $isUp, ?string $error = null): array
{
    return [
        'check_id' => (string) $monitorId,
        'node' => $nodeId,
        'round_id' => $roundId,
        'ok' => $isUp ? '1' : '0',
        'ms' => '100',
        'status_code' => $isUp ? '200' : '0',
        'error' => $error ?? '',
    ];
}

test('auto-registers a probe when a result arrives', function () {
    $monitor = makeMonitor();
    $consumer = new ResultConsumer;

    $consumer->processResult(resultFields($monitor->id, 'new-probe', Str::uuid()->toString(), true));

    $this->assertDatabaseHas('probe_nodes', [
        'node_id' => 'new-probe',
        'active' => true,
    ]);
});

test('single probe: one down result marks monitor down immediately', function () {
    Notification::fake();

    $monitor = makeMonitor();
    ProbeNode::factory()->create(['node_id' => 'only-probe']);

    $consumer = new ResultConsumer;
    $consumer->processResult(resultFields($monitor->id, 'only-probe', 'r1', false, 'Connection refused'));

    expect($monitor->fresh()->status)->toBe('down');
    Notification::assertSentTo($monitor->user->defaultNotificationChannel(), MonitorDown::class);
});

test('two probes: one down + one up = no decision until both in', function () {
    $monitor = makeMonitor();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);
    ProbeNode::factory()->create(['node_id' => 'probe-b']);

    $consumer = new ResultConsumer;
    $consumer->processResult(resultFields($monitor->id, 'probe-a', 'r1', false));

    // With 2 probes, majority = 2. One down doesn't reach majority; wait.
    expect($monitor->fresh()->status)->toBe('up');
});

test('three probes: 2 of 3 down reaches majority and marks down', function () {
    Notification::fake();

    $monitor = makeMonitor();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);
    ProbeNode::factory()->create(['node_id' => 'probe-b']);
    ProbeNode::factory()->create(['node_id' => 'probe-c']);

    $consumer = new ResultConsumer;
    $consumer->processResult(resultFields($monitor->id, 'probe-a', 'r1', false, 'timeout'));
    $consumer->processResult(resultFields($monitor->id, 'probe-b', 'r1', false, 'timeout'));

    expect($monitor->fresh()->status)->toBe('down');
    Notification::assertSentTimes(MonitorDown::class, 1);
});

test('three probes: 1 of 3 down does not cross majority', function () {
    Notification::fake();

    $monitor = makeMonitor();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);
    ProbeNode::factory()->create(['node_id' => 'probe-b']);
    ProbeNode::factory()->create(['node_id' => 'probe-c']);

    $consumer = new ResultConsumer;
    $consumer->processResult(resultFields($monitor->id, 'probe-a', 'r1', false));

    expect($monitor->fresh()->status)->toBe('up');
    Notification::assertNothingSent();
});

test('three probes: once majority down, later up result does not re-decide the same round', function () {
    Notification::fake();

    $monitor = makeMonitor();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);
    ProbeNode::factory()->create(['node_id' => 'probe-b']);
    ProbeNode::factory()->create(['node_id' => 'probe-c']);

    $consumer = new ResultConsumer;
    $consumer->processResult(resultFields($monitor->id, 'probe-a', 'r1', false));
    $consumer->processResult(resultFields($monitor->id, 'probe-b', 'r1', false));
    // Now late-arriving up from probe-c on same round — should be ignored (round already decided).
    $consumer->processResult(resultFields($monitor->id, 'probe-c', 'r1', true));

    expect($monitor->fresh()->status)->toBe('down');
});

test('new round with majority up after majority down triggers recovery', function () {
    Notification::fake();

    $monitor = makeMonitor();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);
    ProbeNode::factory()->create(['node_id' => 'probe-b']);

    $consumer = new ResultConsumer;
    // Round 1: both down → monitor becomes down
    $consumer->processResult(resultFields($monitor->id, 'probe-a', 'r1', false));
    $consumer->processResult(resultFields($monitor->id, 'probe-b', 'r1', false));
    expect($monitor->fresh()->status)->toBe('down');

    // Round 2: both up → monitor recovers
    $consumer->processResult(resultFields($monitor->id, 'probe-a', 'r2', true));
    $consumer->processResult(resultFields($monitor->id, 'probe-b', 'r2', true));
    expect($monitor->fresh()->status)->toBe('up');

    Notification::assertSentTimes(MonitorDown::class, 1);
    Notification::assertSentTimes(MonitorRecovered::class, 1);
});

test('consecutive_failures threshold still applies on top of quorum', function () {
    Notification::fake();

    $monitor = makeMonitor(['failure_threshold' => 2]);
    ProbeNode::factory()->create(['node_id' => 'probe-a']);

    $consumer = new ResultConsumer;
    // First round's quorum-down should NOT flip status (threshold=2 means need 2 consecutive)
    $consumer->processResult(resultFields($monitor->id, 'probe-a', 'r1', false));
    expect($monitor->fresh()->status)->toBe('up');

    // Second round's quorum-down crosses threshold
    $consumer->processResult(resultFields($monitor->id, 'probe-a', 'r2', false));
    expect($monitor->fresh()->status)->toBe('down');
});

test('stale probes are not counted in quorum denominator', function () {
    Notification::fake();

    $monitor = makeMonitor();
    // One active probe and one stale probe (hasn't reported recently).
    ProbeNode::factory()->create(['node_id' => 'probe-a']);
    ProbeNode::factory()->stale()->create(['node_id' => 'probe-stale']);

    $consumer = new ResultConsumer;
    // probe-a reports down; with stale probe excluded, majority = 1, so this alone is decisive.
    $consumer->processResult(resultFields($monitor->id, 'probe-a', 'r1', false));

    expect($monitor->fresh()->status)->toBe('down');
});

test('legacy result without round_id still uses phase-1 single-observer logic', function () {
    Notification::fake();

    $monitor = makeMonitor();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);

    $consumer = new ResultConsumer;
    $consumer->processResult([
        'check_id' => (string) $monitor->id,
        'node' => 'probe-a',
        'ok' => '0',
        'ms' => '100',
        'error' => 'fail',
    ]);

    expect($monitor->fresh()->status)->toBe('down');
});

test('active probe count excludes stale ones', function () {
    ProbeNode::factory()->count(2)->create();
    ProbeNode::factory()->stale()->create();
    ProbeNode::factory()->inactive()->create();

    expect(ProbeNode::activeCount())->toBe(2);
});
