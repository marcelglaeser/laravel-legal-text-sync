<?php

use App\Support\WebhookSignature;

test('signs a payload with hmac sha256', function () {
    expect(WebhookSignature::sign('{"foo":"bar"}', 'secret'))
        ->toBe('sha256='.hash_hmac('sha256', '{"foo":"bar"}', 'secret'));
});

test('verifies a valid signature', function () {
    $signature = WebhookSignature::sign('payload', 'secret');

    expect(WebhookSignature::verify('payload', 'secret', $signature))->toBeTrue();
});

test('rejects tampered payloads, wrong secrets and missing signatures', function (string $payload, string $secret, ?string $signature) {
    expect(WebhookSignature::verify($payload, $secret, $signature))->toBeFalse();
})->with([
    'tampered payload' => ['payload!', 'secret', WebhookSignature::sign('payload', 'secret')],
    'wrong secret' => ['payload', 'other-secret', WebhookSignature::sign('payload', 'secret')],
    'missing signature' => ['payload', 'secret', null],
]);
