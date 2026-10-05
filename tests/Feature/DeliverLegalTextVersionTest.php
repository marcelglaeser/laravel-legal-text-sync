<?php

use App\Enums\DeliveryStatus;
use App\Jobs\DeliverLegalTextVersion;
use App\Models\Delivery;
use App\Models\Shop;
use App\Support\WebhookSignature;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('delivers to a generic webhook with a valid hmac signature', function () {
    Http::fake(['https://93.184.215.14/*' => Http::response(['received' => true])]);

    $delivery = Delivery::factory()
        ->for(Shop::factory()->state(['endpoint_url' => 'https://93.184.215.14/legal', 'secret' => 'shop-secret']))
        ->create();

    DeliverLegalTextVersion::dispatchSync($delivery);

    expect($delivery->refresh())
        ->status->toBe(DeliveryStatus::Delivered)
        ->attempts->toBe(1)
        ->delivered_at->not->toBeNull();

    Http::assertSent(fn (Request $request) => $request->url() === 'https://93.184.215.14/legal'
        && WebhookSignature::verify($request->body(), 'shop-secret', $request->header(WebhookSignature::HEADER)[0] ?? null)
        && $request['version'] === $delivery->legalTextVersion->version
        && $request['content'] === $delivery->legalTextVersion->content
        && $request->hasHeader('Idempotency-Key', "delivery-{$delivery->id}"));
});

test('an endpoint that now points to an internal address fails immediately without a request', function () {
    $delivery = Delivery::factory()
        ->for(Shop::factory()->state(['endpoint_url' => 'http://localhost:6379/hook']))
        ->create();

    DeliverLegalTextVersion::dispatchSync($delivery);

    expect($delivery->refresh())
        ->status->toBe(DeliveryStatus::Failed)
        ->attempts->toBe(1)
        ->last_error->toContain('non-public');

    Http::assertNothingSent();
});

test('the built-in mock shop is reachable although it runs on the app host', function () {
    config(['services.mock_shop.failure_rate' => 0.0]);
    Http::fake();

    $shop = Shop::factory()->create();
    $shop->update(['endpoint_url' => route('mock-shop', $shop)]);
    $delivery = Delivery::factory()->for($shop)->create();

    DeliverLegalTextVersion::dispatchSync($delivery);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Delivered);

    Http::assertSent(fn (Request $request) => $request->url() === route('mock-shop', $shop));
});

test('a redirect is not treated as a successful delivery', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/'])]);

    $delivery = Delivery::factory()->create();

    expect(fn () => (new DeliverLegalTextVersion($delivery))->handle())
        ->toThrow(RequestException::class);

    expect($delivery->refresh())
        ->status->toBe(DeliveryStatus::Pending)
        ->last_error->toContain('302');

    Http::assertSentCount(1);
});

test('delivers to shopify and jtl through their mock adapters without http calls', function (string $type) {
    $delivery = Delivery::factory()->for(Shop::factory()->state(['type' => $type]))->create();

    DeliverLegalTextVersion::dispatchSync($delivery);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Delivered);

    Http::assertNothingSent();
})->with(['shopify', 'jtl']);

test('a shop error keeps the delivery pending and rethrows so the queue retries', function () {
    Http::fake(['*' => Http::response('Server Error', 500)]);

    $delivery = Delivery::factory()->create();

    expect(fn () => (new DeliverLegalTextVersion($delivery))->handle())
        ->toThrow(RequestException::class);

    expect($delivery->refresh())
        ->status->toBe(DeliveryStatus::Pending)
        ->attempts->toBe(1)
        ->last_error->toContain('500');
});

test('retries with backoff and marks the delivery as failed after the last attempt', function () {
    config(['queue.default' => 'database']);
    Http::fake(['*' => Http::response('Service Unavailable', 503)]);

    $delivery = Delivery::factory()->create();
    DeliverLegalTextVersion::dispatch($delivery);

    $job = new DeliverLegalTextVersion($delivery);

    foreach ([0, ...$job->backoff()] as $delay) {
        $this->travel($delay + 1)->seconds();
        Artisan::call('queue:work', ['--once' => true, '--sleep' => 0]);
    }

    expect($delivery->refresh())
        ->status->toBe(DeliveryStatus::Failed)
        ->attempts->toBe($job->tries)
        ->last_error->toContain('503')
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(DB::table('failed_jobs')->count())->toBe(1);

    Http::assertSentCount($job->tries);
});

test('an already delivered delivery is not sent again', function () {
    $delivery = Delivery::factory()->create([
        'status' => DeliveryStatus::Delivered,
        'attempts' => 1,
        'delivered_at' => now(),
    ]);

    DeliverLegalTextVersion::dispatchSync($delivery);

    expect($delivery->refresh()->attempts)->toBe(1);

    Http::assertNothingSent();
});

test('a failed delivery can be retried', function () {
    Queue::fake();

    $delivery = Delivery::factory()->failed()->create();

    $delivery->retry();

    expect($delivery->refresh())
        ->status->toBe(DeliveryStatus::Pending)
        ->last_error->toBeNull();

    Queue::assertPushed(DeliverLegalTextVersion::class, fn ($job) => $job->delivery->is($delivery));
});

test('retrying the same failed delivery twice only queues one job', function () {
    Queue::fake();

    $delivery = Delivery::factory()->failed()->create();

    expect($delivery->retry())->toBeTrue()
        ->and(Delivery::find($delivery->id)?->retry())->toBeFalse();

    Queue::assertPushed(DeliverLegalTextVersion::class, 1);
});

test('only failed deliveries can be retried', function () {
    Queue::fake();

    Delivery::factory()->create()->retry();

    Queue::assertNothingPushed();
});
