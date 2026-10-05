<?php

use App\Exceptions\UnsafeEndpointException;
use App\Support\PublicEndpoint;

test('accepts public addresses', function (string $url, string $ip, int $port) {
    $endpoint = PublicEndpoint::resolve($url);

    expect($endpoint->ip)->toBe($ip)
        ->and($endpoint->port)->toBe($port);
})->with([
    'ipv4 https' => ['https://93.184.215.14/hook', '93.184.215.14', 443],
    'ipv4 http with port' => ['http://93.184.215.14:8080/hook', '93.184.215.14', 8080],
    'ipv6' => ['https://[2606:4700:4700::1111]/hook', '2606:4700:4700::1111', 443],
]);

test('rejects internal, reserved and malformed targets', function (string $url) {
    PublicEndpoint::resolve($url);
})->throws(UnsafeEndpointException::class)->with([
    'loopback' => 'http://127.0.0.1/hook',
    'localhost' => 'http://localhost:8080/hook',
    'private network' => 'http://10.0.0.5/hook',
    'home network' => 'http://192.168.1.1/hook',
    'cloud metadata' => 'http://169.254.169.254/latest/meta-data',
    'carrier-grade nat' => 'http://100.64.0.1/hook',
    'unspecified' => 'http://0.0.0.0/hook',
    'ipv6 loopback' => 'http://[::1]/hook',
    'ipv4-mapped ipv6' => 'http://[::ffff:127.0.0.1]/hook',
    'documentation range' => 'http://203.0.113.10/hook',
    'unresolvable host' => 'https://does-not-exist.invalid/hook',
    'other scheme' => 'ftp://93.184.215.14/hook',
    'file scheme' => 'file:///etc/passwd',
    'credentials' => 'https://user:secret@93.184.215.14/hook',
]);

test('pins hostnames to the verified address for curl', function () {
    $endpoint = PublicEndpoint::resolve('https://[2606:4700:4700::1111]/hook');

    expect($endpoint->curlResolveEntry())->toBe('2606:4700:4700::1111:443:[2606:4700:4700::1111]')
        ->and($endpoint->isIpLiteral())->toBeTrue();
});
