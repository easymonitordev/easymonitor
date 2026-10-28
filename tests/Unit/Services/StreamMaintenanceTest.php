<?php

declare(strict_types=1);

use App\Services\MonitoringEngine\ResultConsumer;
use App\Services\MonitoringEngine\StreamMaintenance;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    Redis::shouldReceive('connection')->with('streams')->andReturnSelf();
});

it('trims both monitoring streams approximately to the configured length', function () {
    config(['easymonitor.streams.max_length' => 500]);

    Redis::shouldReceive('xtrim')->with('checks', '500', true)->andReturn(12, 0);
    Redis::shouldReceive('xtrim')->with('results', '500', true)->andReturn(34, 0);

    expect((new StreamMaintenance)->trimStreams())->toBe(['checks' => 12, 'results' => 34]);
});

it('keeps trimming until a large backlog is gone', function () {
    Redis::shouldReceive('xtrim')->with('checks', '10000', true)->andReturn(0);
    Redis::shouldReceive('xtrim')->with('results', '10000', true)->times(4)->andReturn(10000, 10000, 4200, 0);

    expect((new StreamMaintenance)->trimStreams())->toBe(['checks' => 0, 'results' => 24200]);
});

it('deletes only result consumers idle longer than the threshold', function () {
    config(['easymonitor.streams.consumer_idle_minutes' => 60]);

    Redis::shouldReceive('xinfo')->with('GROUPS', 'results')->andReturn([
        ['name' => 'result-processors', 'consumers' => 3, 'last-delivered-id' => '5-0'],
    ]);
    Redis::shouldReceive('xinfo')
        ->with('CONSUMERS', ResultConsumer::STREAM_RESULTS, ResultConsumer::CONSUMER_GROUP)
        ->andReturn([
            ['name' => 'worker-old-1', 'pending' => 0, 'idle' => 61 * 60 * 1000],
            ['name' => 'worker-current', 'pending' => 2, 'idle' => 5000],
            ['name' => 'worker-old-2', 'pending' => 3, 'idle' => 900 * 60 * 1000],
        ]);

    Redis::shouldReceive('xgroup')->once()->with('DELCONSUMER', 'results', 'result-processors', 'worker-old-1');
    Redis::shouldReceive('xgroup')->once()->with('DELCONSUMER', 'results', 'result-processors', 'worker-old-2');

    expect((new StreamMaintenance)->pruneIdleResultConsumers())->toBe(2);
});

it('deletes nothing when the results stream has no consumer group yet', function () {
    Redis::shouldReceive('xinfo')->andReturn(false);
    Redis::shouldReceive('xgroup')->never();

    expect((new StreamMaintenance)->pruneIdleResultConsumers())->toBe(0);
});

it('recreates an oversized group at its last delivered position instead of listing it', function () {
    Redis::shouldReceive('xinfo')->with('GROUPS', 'results')->andReturn([
        ['name' => 'some-other-group', 'consumers' => 1, 'last-delivered-id' => '1-0'],
        ['name' => 'result-processors', 'consumers' => 1466482, 'last-delivered-id' => '1793177753468-0'],
    ]);
    Redis::shouldReceive('xinfo')->with('CONSUMERS', 'results', 'result-processors')->never();

    Redis::shouldReceive('xgroup')->once()->ordered()->with('DESTROY', 'results', 'result-processors');
    Redis::shouldReceive('xgroup')->once()->ordered()->with('CREATE', 'results', 'result-processors', '1793177753468-0');

    expect((new StreamMaintenance)->pruneIdleResultConsumers())->toBe(1466482);
});

it('reads results under the same consumer name from every worker process', function () {
    $consumerNames = [];

    Redis::shouldReceive('xgroup')->andReturn(true);
    Redis::shouldReceive('xreadgroup')->andReturnUsing(function (string $group, string $consumer) use (&$consumerNames) {
        $consumerNames[] = $consumer;

        return [];
    });

    (new ResultConsumer)->processResults(10, 0);
    (new ResultConsumer)->processResults(10, 0);

    expect($consumerNames)->toBe(['worker-'.gethostname(), 'worker-'.gethostname()]);
});
