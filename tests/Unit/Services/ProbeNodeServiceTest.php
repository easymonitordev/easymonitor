<?php

declare(strict_types=1);

use App\Services\ProbeNodeService;

beforeEach(function () {
    // Set a test JWT secret
    config(['app.jwt_secret' => 'test-secret-key-for-testing-only']);
});

test('can generate jwt token for probe node', function () {
    $service = new ProbeNodeService;

    $token = $service->generateToken('test-node-1', ['us-east-1', 'production'], 365);

    expect($token)->toBeString()
        ->and($token)->not->toBeEmpty();
});

test('can validate jwt token', function () {
    $service = new ProbeNodeService;

    $token = $service->generateToken('test-node-1', ['us-east-1'], 365);
    $payload = $service->validateToken($token);

    expect($payload->node_id)->toBe('test-node-1')
        ->and($payload->tags)->toBe(['us-east-1']);
});

test('can extract node id from token', function () {
    $service = new ProbeNodeService;

    $token = $service->generateToken('my-probe-node', [], 365);
    $nodeId = $service->getNodeIdFromToken($token);

    expect($nodeId)->toBe('my-probe-node');
});

test('throws exception when jwt secret is not configured', function () {
    config(['app.jwt_secret' => null]);

    $service = new ProbeNodeService;

    $service->generateToken('test-node', [], 365);
})->throws(RuntimeException::class, 'JWT_SECRET is not configured');

test('throws exception when validating invalid token', function () {
    $service = new ProbeNodeService;

    $service->validateToken('invalid.token.here');
})->throws(Exception::class);

test('throws exception when validating token with wrong secret', function () {
    $service = new ProbeNodeService;

    // Generate with one secret
    $token = $service->generateToken('test-node', [], 365);

    // Try to validate with different secret
    config(['app.jwt_secret' => 'different-secret']);

    $service->validateToken($token);
})->throws(Exception::class);

test('generated token includes expiration time', function () {
    $service = new ProbeNodeService;

    $token = $service->generateToken('test-node', [], 30);
    $payload = $service->validateToken($token);

    expect($payload->exp)->toBeInt()
        ->and($payload->exp)->toBeGreaterThan(time());
});

test('can generate token with tags', function () {
    $service = new ProbeNodeService;

    $tags = ['region-us-east', 'tier-production', 'datacenter-1'];
    $token = $service->generateToken('tagged-node', $tags, 365);
    $payload = $service->validateToken($token);

    expect($payload->tags)->toBe($tags);
});

test('can generate token without tags', function () {
    $service = new ProbeNodeService;

    $token = $service->generateToken('simple-node', [], 365);
    $payload = $service->validateToken($token);

    expect($payload->tags)->toBe([]);
});
