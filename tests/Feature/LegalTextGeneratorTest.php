<?php

use App\Enums\DeliveryStatus;
use App\Enums\LegalTextType;
use App\Events\LegalTextVersionPublished;
use App\Models\LegalTemplateVersion;
use App\Models\MerchantProfile;
use App\Models\Shop;
use App\Services\LegalTextGenerator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

test('without approval the generated text goes live immediately', function () {
    Event::fake([LegalTextVersionPublished::class]);

    $profile = MerchantProfile::factory()->create(['company_name' => 'Musterhändler GmbH']);

    $version = app(LegalTextGenerator::class)->generate($profile, publishedTemplate());

    expect($version)
        ->content->toBe('Impressum der Musterhändler GmbH')
        ->isPublished()->toBeTrue();

    Event::assertDispatched(LegalTextVersionPublished::class);
});

test('with approval the generated text waits for the merchant', function () {
    Event::fake([LegalTextVersionPublished::class]);

    $profile = MerchantProfile::factory()->requiresApproval()->create();

    $version = app(LegalTextGenerator::class)->generate($profile, publishedTemplate());

    expect($version->isPublished())->toBeFalse()
        ->and($version->isAwaitingApproval())->toBeTrue();

    Event::assertNotDispatched(LegalTextVersionPublished::class);
});

test('regenerating with unchanged data creates no new version', function () {
    $profile = MerchantProfile::factory()->create();
    $template = publishedTemplate();
    $generator = app(LegalTextGenerator::class);

    $first = $generator->generate($profile, $template);
    $second = $generator->generate($profile, $template);

    expect($second->is($first))->toBeTrue();
});

test('generates texts for every published template only', function () {
    $profile = MerchantProfile::factory()->create();
    publishedTemplate(LegalTextType::Imprint);
    publishedTemplate(LegalTextType::Terms);
    LegalTemplateVersion::draft(LegalTextType::Privacy, 'Entwurf');

    app(LegalTextGenerator::class)->generateAll($profile);

    expect($profile->user->legalTexts()->has('versions')->pluck('type')->all())
        ->toEqualCanonicalizing([LegalTextType::Imprint, LegalTextType::Terms]);
});

test('a template update flows through to all shops of every merchant', function () {
    Http::preventStrayRequests();

    $profile = MerchantProfile::factory()->create();
    $shop = Shop::factory()->shopify()->for($profile->user)->create();

    LegalTemplateVersion::draft(LegalTextType::Terms, 'AGB der {{ company_name }}')->publish();

    $version = $profile->user->legalText(LegalTextType::Terms)->currentVersion;

    expect($version?->content)->toBe("AGB der {$profile->company_name}")
        ->and($shop->deliveries()->sole()->status)->toBe(DeliveryStatus::Delivered);
});
