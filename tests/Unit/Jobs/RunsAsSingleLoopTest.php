<?php

declare(strict_types=1);

use App\Jobs\MonitoringEngine\DispatchMonitorChecks;
use App\Jobs\MonitoringEngine\ProcessMonitorResults;
use App\Services\MonitoringEngine\CheckDispatcher;
use App\Services\MonitoringEngine\ResultConsumer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();

    $this->dispatcher = Mockery::mock(CheckDispatcher::class);
    $this->dispatcher->shouldReceive('ensureConsumerGroupExists');

    $this->consumer = Mockery::mock(ResultConsumer::class);
    $this->consumer->shouldReceive('ensureConsumerGroupExists');
    $this->consumer->shouldReceive('processResults')->andReturn(0);
});

/**
 * Run a loop job the way the queue would
 */
function runLoopJob(DispatchMonitorChecks|ProcessMonitorResults $job): void
{
    $job instanceof DispatchMonitorChecks
        ? $job->handle(test()->dispatcher)
        : $job->handle(test()->consumer);
}

it('starting a loop registers a new chain and dispatches its first job', function (string $jobClass) {
    $jobClass::startLoop();

    Queue::assertPushed($jobClass, 1);
    Queue::assertPushed($jobClass, fn ($job) => $job->loopId !== null);
})->with([DispatchMonitorChecks::class, ProcessMonitorResults::class]);

it('the current chain keeps requeueing itself with its loop id', function (string $jobClass) {
    $jobClass::startLoop();
    $first = Queue::pushed($jobClass)->first();

    runLoopJob($first);

    Queue::assertPushed($jobClass, 2);
    expect(Queue::pushed($jobClass)->last()->loopId)->toBe($first->loopId);
})->with([DispatchMonitorChecks::class, ProcessMonitorResults::class]);

it('a superseded chain stops without doing work or requeueing', function () {
    DispatchMonitorChecks::startLoop();
    $old = Queue::pushed(DispatchMonitorChecks::class)->first();

    DispatchMonitorChecks::startLoop();

    // A bare mock fails on any call, so this also proves no work was done.
    $old->handle(Mockery::mock(CheckDispatcher::class));

    Queue::assertPushed(DispatchMonitorChecks::class, 2);
});

it('leftover chains from before the upgrade collapse into one', function () {
    $legacyJobs = collect(range(1, 6))->map(fn () => new ProcessMonitorResults);

    $legacyJobs->each(fn ($job) => runLoopJob($job));

    Queue::assertPushed(ProcessMonitorResults::class, 1);
});

it('a lost loop id is adopted so monitoring never stops', function () {
    ProcessMonitorResults::startLoop();
    $job = Queue::pushed(ProcessMonitorResults::class)->first();

    Cache::flush();
    runLoopJob($job);

    Queue::assertPushed(ProcessMonitorResults::class, 2);
    expect(Queue::pushed(ProcessMonitorResults::class)->last()->loopId)->toBe($job->loopId);
});

it('the current chain survives losing the lock to a superseded chain', function () {
    DispatchMonitorChecks::startLoop();
    $current = Queue::pushed(DispatchMonitorChecks::class)->first();

    Cache::lock('monitor:dispatch-checks:lock', 60)->get();
    runLoopJob($current);

    Queue::assertPushed(DispatchMonitorChecks::class, 2);
});

it('only the current chain requeues after a final failure', function () {
    DispatchMonitorChecks::startLoop();
    $old = Queue::pushed(DispatchMonitorChecks::class)->first();
    DispatchMonitorChecks::startLoop();
    $current = Queue::pushed(DispatchMonitorChecks::class)->last();

    $old->failed(new RuntimeException('boom'));
    Queue::assertPushed(DispatchMonitorChecks::class, 2);

    $current->failed(new RuntimeException('boom'));
    Queue::assertPushed(DispatchMonitorChecks::class, 3);
});
