<?php

declare(strict_types=1);

use App\Services\MonitoringEngine\EngineHealth;
use Illuminate\Support\Facades\Cache;

it('reports components as healthy when heartbeats are fresh', function () {
    Cache::put('monitor:dispatch-checks:last-run', now()->subSeconds(30), 3600);
    Cache::put('monitor:process-results:last-run', now()->subSeconds(45), 3600);

    $statuses = (new EngineHealth)->componentStatuses();

    expect($statuses)->toHaveCount(2)
        ->and(collect($statuses)->pluck('status')->unique()->all())->toBe([EngineHealth::STATUS_HEALTHY])
        ->and((new EngineHealth)->stalledComponents())->toBeEmpty();
});

it('reports a component as stalled when its heartbeat is older than the threshold', function () {
    Cache::put('monitor:dispatch-checks:last-run', now()->subSeconds(300), 3600);
    Cache::put('monitor:process-results:last-run', now()->subSeconds(10), 3600);

    $stalled = (new EngineHealth)->stalledComponents();

    expect($stalled)->toHaveCount(1)
        ->and($stalled[0]['component'])->toBe(EngineHealth::COMPONENT_DISPATCHER)
        ->and($stalled[0]['message'])->toBe('checks are not being dispatched')
        ->and($stalled[0]['seconds_since_last_run'])->toBeGreaterThanOrEqual(300);
});

it('reports unknown when a heartbeat has never been written', function () {
    $statuses = (new EngineHealth)->componentStatuses();

    expect(collect($statuses)->pluck('status')->unique()->all())->toBe([EngineHealth::STATUS_UNKNOWN])
        ->and((new EngineHealth)->stalledComponents())->toBeEmpty();
});

it('is not fooled by the exact threshold boundary', function () {
    Cache::put('monitor:dispatch-checks:last-run', now()->subSeconds(EngineHealth::STALL_THRESHOLD_SECONDS), 3600);

    $dispatcher = collect((new EngineHealth)->componentStatuses())
        ->firstWhere('component', EngineHealth::COMPONENT_DISPATCHER);

    expect($dispatcher['status'])->toBe(EngineHealth::STATUS_HEALTHY);
});
