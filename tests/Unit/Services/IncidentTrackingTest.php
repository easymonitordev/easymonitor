<?php

use App\Models\Incident;
use App\Models\Monitor;
use App\Models\ProbeNode;
use App\Models\User;
use App\Services\MonitoringEngine\ResultConsumer;
use Illuminate\Support\Facades\Notification;

function makeMonitorForIncidents(array $overrides = []): Monitor
{
    $user = User::factory()->create();

    return Monitor::factory()->create(array_merge([
        'user_id' => $user->id,
        'status' => 'up',
        'consecutive_failures' => 0,
        'failure_threshold' => 1,
    ], $overrides));
}

function incidentResult(int $monitorId, string $nodeId, string $roundId, bool $isUp, ?string $error = null, ?int $status = null): array
{
    return [
        'check_id' => (string) $monitorId,
        'node' => $nodeId,
        'round_id' => $roundId,
        'ok' => $isUp ? '1' : '0',
        'ms' => '100',
        'status_code' => (string) ($status ?? ($isUp ? 200 : 0)),
        'error' => $error ?? '',
    ];
}

test('opens an incident when monitor transitions to down', function () {
    Notification::fake();

    $monitor = makeMonitorForIncidents();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);

    (new ResultConsumer)->processResult(
        incidentResult($monitor->id, 'probe-a', 'r1', false, 'Connection refused', 503)
    );

    expect($monitor->fresh()->status)->toBe('down');

    $incident = Incident::where('monitor_id', $monitor->id)->firstOrFail();
    expect($incident->isOngoing())->toBeTrue()
        ->and($incident->error_message)->toBe('Connection refused')
        ->and($incident->status_code)->toBe(503)
        ->and($incident->trigger_node_id)->toBe('probe-a');
});

test('closes the ongoing incident on recovery with duration set', function () {
    Notification::fake();

    $monitor = makeMonitorForIncidents();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);

    $consumer = new ResultConsumer;
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r1', false, 'timeout'));

    $incident = Incident::where('monitor_id', $monitor->id)->firstOrFail();
    expect($incident->isOngoing())->toBeTrue();

    $this->travel(30)->seconds();

    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r2', true));

    $incident->refresh();
    expect($incident->isOngoing())->toBeFalse()
        ->and($incident->ended_at)->not->toBeNull()
        ->and($incident->duration_seconds)->toBeGreaterThanOrEqual(30);
});

test('does not open a second incident if one is already ongoing', function () {
    Notification::fake();

    $monitor = makeMonitorForIncidents();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);

    $consumer = new ResultConsumer;
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r1', false, 'err'));
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r2', false, 'err'));

    expect(Incident::where('monitor_id', $monitor->id)->count())->toBe(1);
});

test('consecutive up/down cycles produce separate incidents', function () {
    Notification::fake();

    $monitor = makeMonitorForIncidents();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);

    $consumer = new ResultConsumer;
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r1', false, 'first'));
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r2', true));
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r3', false, 'second'));

    $incidents = Incident::where('monitor_id', $monitor->id)->orderBy('id')->get();
    expect($incidents)->toHaveCount(2)
        ->and($incidents[0]->ended_at)->not->toBeNull()
        ->and($incidents[0]->error_message)->toBe('first')
        ->and($incidents[1]->isOngoing())->toBeTrue()
        ->and($incidents[1]->error_message)->toBe('second');
});

test('records a degraded incident when one of two probes fails but quorum stays up', function () {
    Notification::fake();

    $monitor = makeMonitorForIncidents();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);
    ProbeNode::factory()->create(['node_id' => 'probe-b']);
    ProbeNode::factory()->create(['node_id' => 'probe-c']);

    $consumer = new ResultConsumer;
    // 1 of 3 down — quorum stays up, but this is degraded.
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r1', false, 'dns'));
    $consumer->processResult(incidentResult($monitor->id, 'probe-b', 'r1', true));
    $consumer->processResult(incidentResult($monitor->id, 'probe-c', 'r1', true));

    expect($monitor->fresh()->status)->toBe('up');
    Notification::assertNothingSent();

    $incident = Incident::where('monitor_id', $monitor->id)->firstOrFail();
    expect($incident->severity)->toBe(Incident::SEVERITY_DEGRADED)
        ->and($incident->isOngoing())->toBeTrue()
        ->and($incident->affected_node_ids)->toBe(['probe-a']);
});

test('degraded incident closes when next round reports all probes up', function () {
    Notification::fake();

    $monitor = makeMonitorForIncidents();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);
    ProbeNode::factory()->create(['node_id' => 'probe-b']);
    ProbeNode::factory()->create(['node_id' => 'probe-c']);

    $consumer = new ResultConsumer;
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r1', false));
    $consumer->processResult(incidentResult($monitor->id, 'probe-b', 'r1', true));
    $consumer->processResult(incidentResult($monitor->id, 'probe-c', 'r1', true));

    expect(Incident::where('monitor_id', $monitor->id)->whereNull('ended_at')->count())->toBe(1);

    // Round 2: all probes up → degraded closes.
    $this->travel(15)->seconds();
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r2', true));
    $consumer->processResult(incidentResult($monitor->id, 'probe-b', 'r2', true));
    $consumer->processResult(incidentResult($monitor->id, 'probe-c', 'r2', true));

    $incident = Incident::where('monitor_id', $monitor->id)->firstOrFail();
    expect($incident->isOngoing())->toBeFalse()
        ->and($incident->severity)->toBe(Incident::SEVERITY_DEGRADED)
        ->and($incident->duration_seconds)->toBeGreaterThanOrEqual(15);
});

test('degraded incident escalates in place when quorum flips to down', function () {
    Notification::fake();

    $monitor = makeMonitorForIncidents();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);
    ProbeNode::factory()->create(['node_id' => 'probe-b']);
    ProbeNode::factory()->create(['node_id' => 'probe-c']);

    $consumer = new ResultConsumer;
    // Degraded: 1/3 down.
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r1', false));
    $consumer->processResult(incidentResult($monitor->id, 'probe-b', 'r1', true));
    $consumer->processResult(incidentResult($monitor->id, 'probe-c', 'r1', true));

    // Next round: 2/3 down crosses quorum.
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r2', false, 'still down'));
    $consumer->processResult(incidentResult($monitor->id, 'probe-b', 'r2', false, 'still down'));

    expect($monitor->fresh()->status)->toBe('down');

    $incidents = Incident::where('monitor_id', $monitor->id)->get();
    expect($incidents)->toHaveCount(1);
    expect($incidents->first()->severity)->toBe(Incident::SEVERITY_DOWN);
    expect($incidents->first()->affected_node_ids)->toContain('probe-a', 'probe-b');
});

test('degraded signal is ignored while a down incident is ongoing', function () {
    Notification::fake();

    $monitor = makeMonitorForIncidents();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);
    ProbeNode::factory()->create(['node_id' => 'probe-b']);
    ProbeNode::factory()->create(['node_id' => 'probe-c']);

    $consumer = new ResultConsumer;
    // Make it down.
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r1', false));
    $consumer->processResult(incidentResult($monitor->id, 'probe-b', 'r1', false));
    expect($monitor->fresh()->status)->toBe('down');

    // A later partial round should not create a second incident.
    $consumer->processResult(incidentResult($monitor->id, 'probe-a', 'r2', false));
    $consumer->processResult(incidentResult($monitor->id, 'probe-b', 'r2', true));
    $consumer->processResult(incidentResult($monitor->id, 'probe-c', 'r2', true));

    expect(Incident::where('monitor_id', $monitor->id)->count())->toBe(1);
});

test('legacy results without round_id also open and close incidents', function () {
    Notification::fake();

    $monitor = makeMonitorForIncidents();
    ProbeNode::factory()->create(['node_id' => 'probe-a']);

    $consumer = new ResultConsumer;
    $consumer->processResult([
        'check_id' => (string) $monitor->id,
        'node' => 'probe-a',
        'ok' => '0',
        'ms' => '100',
        'error' => 'legacy fail',
    ]);

    expect(Incident::where('monitor_id', $monitor->id)->whereNull('ended_at')->count())->toBe(1);

    $consumer->processResult([
        'check_id' => (string) $monitor->id,
        'node' => 'probe-a',
        'ok' => '1',
        'ms' => '100',
    ]);

    expect(Incident::where('monitor_id', $monitor->id)->whereNull('ended_at')->count())->toBe(0);
});
