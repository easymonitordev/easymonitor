<?php

declare(strict_types=1);

use App\Enums\CheckType;
use App\Models\Monitor;
use App\Services\MonitoringEngine\CheckDispatcher;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->captured = null;

    Redis::shouldReceive('connection')->with('streams')->andReturnSelf();
    Redis::shouldReceive('xadd')->andReturnUsing(function (string $stream, string $id, array $fields) {
        $this->captured = $fields;

        return '1-0';
    });
});

test('http monitors are dispatched with their url unchanged', function () {
    $monitor = Monitor::factory()->create([
        'check_type' => CheckType::Http,
        'url' => 'https://example.com',
        'check_interval' => 60,
    ]);

    (new CheckDispatcher)->dispatchCheck($monitor);

    expect($this->captured['url'])->toBe('https://example.com');
});

test('icmp monitors are dispatched with an icmp scheme prefix', function () {
    $monitor = Monitor::factory()->icmp()->create([
        'url' => '1.1.1.1',
        'check_interval' => 60,
    ]);

    (new CheckDispatcher)->dispatchCheck($monitor);

    expect($this->captured['url'])->toBe('icmp://1.1.1.1');
});

test('tcp monitors are dispatched with a tcp scheme prefix', function () {
    $monitor = Monitor::factory()->tcp()->create([
        'url' => 'db.example.com:5432',
        'check_interval' => 60,
    ]);

    (new CheckDispatcher)->dispatchCheck($monitor);

    expect($this->captured['url'])->toBe('tcp://db.example.com:5432');
});

test('every dispatched check carries an explicit check_type field', function (callable $factory, string $expectedType) {
    $monitor = $factory();

    (new CheckDispatcher)->dispatchCheck($monitor);

    expect($this->captured['check_type'])->toBe($expectedType);
})->with([
    'http' => [fn () => Monitor::factory()->create(['check_type' => CheckType::Http, 'url' => 'https://example.com']), 'http'],
    'icmp' => [fn () => Monitor::factory()->icmp()->create(), 'icmp'],
    'tcp' => [fn () => Monitor::factory()->tcp()->create(), 'tcp'],
]);

test('http monitors with a keyword assertion carry assertion fields', function () {
    $monitor = Monitor::factory()->withKeywordAssertion('healthy')->create([
        'check_type' => CheckType::Http,
        'url' => 'https://example.com/health',
    ]);

    (new CheckDispatcher)->dispatchCheck($monitor);

    expect($this->captured['assertion_type'])->toBe('keyword_present')
        ->and($this->captured['assertion_value'])->toBe('healthy');
});

test('monitors without an assertion omit assertion fields entirely', function () {
    $monitor = Monitor::factory()->create([
        'check_type' => CheckType::Http,
        'url' => 'https://example.com',
    ]);

    (new CheckDispatcher)->dispatchCheck($monitor);

    expect($this->captured)->not->toHaveKeys(['assertion_type', 'assertion_value']);
});

test('non-http monitors omit assertion fields even when columns are set', function () {
    $monitor = Monitor::factory()->tcp()->withKeywordAssertion('healthy')->create();

    (new CheckDispatcher)->dispatchCheck($monitor);

    expect($this->captured)->not->toHaveKeys(['assertion_type', 'assertion_value']);
});
