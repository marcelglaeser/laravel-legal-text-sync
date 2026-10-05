<?php

use App\Models\Shop;
use App\Support\WebhookSignature;
use Illuminate\Testing\TestResponse;

function postToMockShop(Shop $shop, string $body, ?string $signature): TestResponse
{
    return test()->call('POST', route('mock-shop', $shop), server: array_filter([
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_SIGNATURE' => $signature,
    ]), content: $body);
}

test('accepts a correctly signed delivery', function () {
    config(['services.mock_shop.failure_rate' => 0.0]);
    $shop = Shop::factory()->create();

    postToMockShop($shop, '{"version":1}', WebhookSignature::sign('{"version":1}', $shop->secret))
        ->assertOk()
        ->assertJson(['received' => true]);
});

test('rejects invalid or missing signatures', function (?string $signature) {
    postToMockShop(Shop::factory()->create(), '{"version":1}', $signature)->assertUnauthorized();
})->with([
    'invalid' => 'sha256=invalid',
    'missing' => null,
]);

test('simulates outages based on the configured failure rate', function () {
    config(['services.mock_shop.failure_rate' => 1.0]);
    $shop = Shop::factory()->create();

    postToMockShop($shop, '{}', WebhookSignature::sign('{}', $shop->secret))->assertServiceUnavailable();
});
