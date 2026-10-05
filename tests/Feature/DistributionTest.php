<?php

use App\Enums\DeliveryStatus;
use App\Enums\LegalTextType;
use App\Events\LegalTextVersionPublished;
use App\Jobs\DeliverLegalTextVersion;
use App\Listeners\DistributeLegalTextVersion;
use App\Models\LegalTextVersion;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;

function publishedVersionFor(User $user): LegalTextVersion
{
    $version = $user->legalText(LegalTextType::Imprint)->createVersion('Impressum');
    $version->forceFill(['published_at' => now()])->save();

    return $version;
}

test('publishing queues the distribution listener', function () {
    Queue::fake();

    $user = User::factory()->create();
    $user->legalText(LegalTextType::Imprint)->createVersion('Impressum')->publish();

    Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === DistributeLegalTextVersion::class);
});

test('distributes a published version to every shop of the merchant, one job per shop', function () {
    Queue::fake();

    $user = User::factory()->create();
    $shops = Shop::factory()->for($user)->createMany([
        ['type' => 'shopify'],
        ['type' => 'jtl'],
        ['type' => 'generic_webhook'],
    ]);
    $foreignShop = Shop::factory()->create();
    $version = publishedVersionFor($user);

    (new DistributeLegalTextVersion)->handle(new LegalTextVersionPublished($version));

    expect($version->deliveries()->pluck('shop_id')->sort()->values()->all())->toBe($shops->pluck('id')->all())
        ->and($version->deliveries()->where('shop_id', $foreignShop->id)->exists())->toBeFalse()
        ->and($version->deliveries()->where('status', DeliveryStatus::Pending)->count())->toBe(3);

    Queue::assertPushed(DeliverLegalTextVersion::class, 3);
});

test('the same version is never delivered twice to the same shop', function () {
    Queue::fake();

    $user = User::factory()->create();
    Shop::factory()->for($user)->count(2)->create();
    $version = publishedVersionFor($user);
    $listener = new DistributeLegalTextVersion;

    $listener->handle(new LegalTextVersionPublished($version));
    $listener->handle(new LegalTextVersionPublished($version));

    expect($version->deliveries()->count())->toBe(2);

    Queue::assertPushed(DeliverLegalTextVersion::class, 2);
});

test('shops added later receive the next published version', function () {
    Queue::fake();

    $user = User::factory()->create();
    Shop::factory()->for($user)->create();
    $listener = new DistributeLegalTextVersion;

    $listener->handle(new LegalTextVersionPublished(publishedVersionFor($user)));
    Shop::factory()->for($user)->create();

    $next = $user->legalText(LegalTextType::Imprint)->createVersion('Impressum v2');
    $next->forceFill(['published_at' => now()])->save();
    $listener->handle(new LegalTextVersionPublished($next));

    expect($next->deliveries()->count())->toBe(2);

    Queue::assertPushed(DeliverLegalTextVersion::class, 3);
});
