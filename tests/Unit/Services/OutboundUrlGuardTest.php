<?php

declare(strict_types=1);

use App\Services\HostResolver;
use App\Services\OutboundUrlGuard;

it('blocks urls that point at non-public addresses', function (string $url) {
    expect(app(OutboundUrlGuard::class)->isAllowed($url))->toBeFalse();
})->with([
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data/',
    'loopback' => 'http://127.0.0.1:6379/',
    'unspecified' => 'http://0.0.0.0:8080/',
    'rfc1918 10/8' => 'http://10.0.0.5/hook',
    'rfc1918 172.16/12' => 'https://172.16.4.2/hook',
    'rfc1918 192.168/16' => 'http://192.168.1.1/admin',
    'cgnat / tailscale' => 'http://100.100.100.100/',
    'ipv6 loopback' => 'http://[::1]/',
    'ipv6 unique local' => 'http://[fd00::1]/',
    'ipv6 link-local' => 'http://[fe80::1]/',
    'ipv4-mapped ipv6' => 'http://[::ffff:169.254.169.254]/',
    'nat64' => 'http://[64:ff9b::a9fe:a9fe]/',
    'multicast' => 'http://224.0.0.1/',
]);

it('blocks hostnames that resolve to internal addresses', function () {
    $this->fakeDns(['internal.example.com' => ['10.1.2.3']]);

    expect(app(OutboundUrlGuard::class)->isAllowed('https://internal.example.com/hook'))->toBeFalse();
});

it('blocks a hostname when any of its addresses is internal', function () {
    $this->fakeDns(['mixed.example.com' => ['93.184.215.14', '127.0.0.1']]);

    expect(app(OutboundUrlGuard::class)->isAllowed('https://mixed.example.com/hook'))->toBeFalse();
});

it('blocks hostnames that do not resolve', function () {
    $this->fakeDns(['nowhere.example.com' => []]);

    expect(app(OutboundUrlGuard::class)->isAllowed('https://nowhere.example.com/hook'))->toBeFalse();
});

it('blocks non-http schemes and malformed urls', function (string $url) {
    expect(app(OutboundUrlGuard::class)->isAllowed($url))->toBeFalse();
})->with(['file:///etc/passwd', 'gopher://example.com/', 'ftp://example.com/', 'not a url', '']);

it('allows public addresses and hostnames', function (string $url) {
    expect(app(OutboundUrlGuard::class)->isAllowed($url))->toBeTrue();
})->with([
    'https://hooks.example.com/notify',
    'http://8.8.8.8/',
    'https://[2606:4700:4700::1111]/',
]);

it('allows private networks when explicitly enabled', function (string $url) {
    config(['easymonitor.outbound.allow_private_networks' => true]);

    expect(app(OutboundUrlGuard::class)->isAllowed($url))->toBeTrue();
})->with(['http://10.0.0.5/hook', 'http://192.168.1.10:8080/', 'http://127.0.0.1:2586/', 'http://100.64.1.2/']);

it('still blocks link-local and metadata addresses when private networks are enabled', function (string $url) {
    config(['easymonitor.outbound.allow_private_networks' => true]);

    expect(app(OutboundUrlGuard::class)->isAllowed($url))->toBeFalse();
})->with([
    'http://169.254.169.254/latest/meta-data/',
    'http://[fe80::1]/',
    'http://[::ffff:169.254.169.254]/',
    'http://0.0.0.0/',
]);

it('pins the request to the checked address and disables redirects', function () {
    $this->fakeDns(['hooks.example.com' => ['93.184.215.14']]);

    $options = app(OutboundUrlGuard::class)->request('https://hooks.example.com/notify')->getOptions();

    expect($options['allow_redirects'])->toBeFalse()
        ->and($options['curl'][CURLOPT_RESOLVE])->toBe(['hooks.example.com:443:93.184.215.14']);
});

it('pins ipv6 addresses and explicit ports', function () {
    $this->fakeDns(['v6.example.com' => ['2606:4700:4700::1111']]);

    $options = app(OutboundUrlGuard::class)->request('http://v6.example.com:8080/notify')->getOptions();

    expect($options['curl'][CURLOPT_RESOLVE])->toBe(['v6.example.com:8080:[2606:4700:4700::1111]']);
});

it('returns no request for a blocked url', function () {
    expect(app(OutboundUrlGuard::class)->request('http://169.254.169.254/'))->toBeNull();
});

it('expands shorthand ipv4 notations to the address curl would connect to', function (string $host) {
    expect((new HostResolver)->resolve($host))->toBe(['127.0.0.1']);
})->with(['127.1', '2130706433', '0x7f.1', '0177.0.0.1']);
