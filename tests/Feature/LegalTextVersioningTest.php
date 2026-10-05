<?php

use App\Enums\LegalTextType;
use App\Events\LegalTextVersionPublished;
use App\Listeners\DistributeLegalTextVersion;
use App\Models\User;
use Illuminate\Support\Facades\Event;

test('every change creates a new version and keeps the old ones', function () {
    $legalText = User::factory()->create()->legalText(LegalTextType::Terms);

    $first = $legalText->createVersion('AGB v1', publishedTemplate());
    $second = $legalText->createVersion('AGB v2', publishedTemplate());

    expect($first->version)->toBe(1)
        ->and($second->version)->toBe(2)
        ->and($legalText->versions()->pluck('content')->all())->toBe(['AGB v1', 'AGB v2']);
});

test('saving unchanged content does not create a new version', function () {
    $legalText = User::factory()->create()->legalText(LegalTextType::Terms);

    $first = $legalText->createVersion('AGB v1', publishedTemplate());
    $same = $legalText->createVersion('AGB v1', publishedTemplate());

    expect($same->is($first))->toBeTrue()
        ->and($legalText->versions()->count())->toBe(1);
});

test('versions are numbered per legal text', function () {
    $user = User::factory()->create();

    $user->legalText(LegalTextType::Terms)->createVersion('AGB', publishedTemplate());
    $imprint = $user->legalText(LegalTextType::Imprint)->createVersion('Impressum', publishedTemplate());

    expect($imprint->version)->toBe(1);
});

test('publishing a version dispatches the published event', function () {
    Event::fake([LegalTextVersionPublished::class]);

    $version = User::factory()->create()->legalText(LegalTextType::Privacy)->createVersion('Datenschutz', publishedTemplate());

    expect($version->publish())->toBeTrue()
        ->and($version->isPublished())->toBeTrue();

    Event::assertDispatched(LegalTextVersionPublished::class, fn ($event) => $event->version->is($version));
});

test('publishing the same version twice only dispatches the event once', function () {
    Event::fake([LegalTextVersionPublished::class]);

    $version = User::factory()->create()->legalText(LegalTextType::Privacy)->createVersion('Datenschutz', publishedTemplate());

    $version->publish();

    expect($version->publish())->toBeFalse();

    Event::assertDispatchedTimes(LegalTextVersionPublished::class, 1);
});

test('the current version is the latest published one, drafts are ignored', function () {
    Event::fake([LegalTextVersionPublished::class]);

    $legalText = User::factory()->create()->legalText(LegalTextType::Withdrawal);

    $legalText->createVersion('v1', publishedTemplate())->publish();
    $this->travel(1)->minute();
    $legalText->createVersion('v2', publishedTemplate())->publish();
    $legalText->createVersion('v3 draft', publishedTemplate());

    expect($legalText->currentVersion?->content)->toBe('v2')
        ->and($legalText->latestVersion?->content)->toBe('v3 draft');
});

test('the distribution listener is registered and queued', function () {
    Event::fake();

    Event::assertListening(LegalTextVersionPublished::class, DistributeLegalTextVersion::class);
});
