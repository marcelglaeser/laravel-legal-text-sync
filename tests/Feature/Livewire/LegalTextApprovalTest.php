<?php

use App\Enums\LegalTextType;
use App\Events\LegalTextVersionPublished;
use App\Models\MerchantProfile;
use App\Services\LegalTextGenerator;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('a merchant approves a pending version, which publishes it', function () {
    $profile = MerchantProfile::factory()->requiresApproval()->create();
    $version = app(LegalTextGenerator::class)->generate($profile, publishedTemplate());

    Event::fake([LegalTextVersionPublished::class]);

    Livewire::actingAs($profile->user)
        ->test('pages::legal-texts.show', ['type' => LegalTextType::Imprint])
        ->assertSee('waiting for your approval')
        ->call('approve', $version->id)
        ->assertOk();

    expect($version->refresh()->isPublished())->toBeTrue();

    Event::assertDispatchedTimes(LegalTextVersionPublished::class, 1);
});

test('the live version stays until the merchant approves', function () {
    $profile = MerchantProfile::factory()->create(['company_name' => 'Alt GmbH']);
    $generator = app(LegalTextGenerator::class);
    $generator->generate($profile, publishedTemplate());

    $profile->update(['requires_approval' => true, 'company_name' => 'Neu GmbH']);
    $generator->generate($profile, publishedTemplate());

    expect($profile->user->legalText(LegalTextType::Imprint)->currentVersion?->content)
        ->toBe('Impressum der Alt GmbH');
});

test('superseded pending versions cannot be approved', function () {
    $profile = MerchantProfile::factory()->requiresApproval()->create(['company_name' => 'Alt GmbH']);
    $generator = app(LegalTextGenerator::class);
    $outdated = $generator->generate($profile, publishedTemplate());
    $profile->update(['company_name' => 'Neu GmbH']);
    $generator->generate($profile, publishedTemplate());

    Livewire::actingAs($profile->user)
        ->test('pages::legal-texts.show', ['type' => LegalTextType::Imprint])
        ->call('approve', $outdated->id)
        ->assertForbidden();

    expect($outdated->refresh()->isPublished())->toBeFalse();
});

test('a merchant cannot approve versions of another merchant', function () {
    $foreign = MerchantProfile::factory()->requiresApproval()->create();
    $version = app(LegalTextGenerator::class)->generate($foreign, publishedTemplate());

    Livewire::actingAs(MerchantProfile::factory()->create()->user)
        ->test('pages::legal-texts.show', ['type' => LegalTextType::Imprint])
        ->call('approve', $version->id)
        ->assertForbidden();

    expect($version->refresh()->isPublished())->toBeFalse();
});
