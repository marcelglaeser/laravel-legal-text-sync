<?php

use App\Enums\LegalTextType;
use App\Events\LegalTextVersionPublished;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('saving the editor creates a new version', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::legal-texts.edit', ['type' => LegalTextType::Terms])
        ->set('content', 'Unsere neuen Allgemeinen Geschäftsbedingungen.')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->legalText(LegalTextType::Terms)->versions()->sole())
        ->version->toBe(1)
        ->content->toBe('Unsere neuen Allgemeinen Geschäftsbedingungen.')
        ->published_at->toBeNull();
});

test('the editor is prefilled with the latest version', function () {
    $user = User::factory()->create();
    $user->legalText(LegalTextType::Imprint)->createVersion('Musterhändler GmbH, Musterstadt');

    Livewire::actingAs($user)
        ->test('pages::legal-texts.edit', ['type' => LegalTextType::Imprint])
        ->assertSet('content', 'Musterhändler GmbH, Musterstadt');
});

test('content is required', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::legal-texts.edit', ['type' => LegalTextType::Terms])
        ->set('content', '')
        ->call('save')
        ->assertHasErrors(['content' => 'required']);
});

test('publishing a version from the editor dispatches the event once', function () {
    Event::fake([LegalTextVersionPublished::class]);

    $user = User::factory()->create();
    $version = $user->legalText(LegalTextType::Terms)->createVersion('Allgemeine Geschäftsbedingungen');

    Livewire::actingAs($user)
        ->test('pages::legal-texts.edit', ['type' => LegalTextType::Terms])
        ->call('publish', $version->id)
        ->call('publish', $version->id)
        ->assertOk();

    expect($version->refresh()->isPublished())->toBeTrue();

    Event::assertDispatchedTimes(LegalTextVersionPublished::class, 1);
});

test('a merchant cannot publish versions of another merchant', function () {
    Event::fake([LegalTextVersionPublished::class]);

    $foreignVersion = User::factory()->create()->legalText(LegalTextType::Terms)->createVersion('Fremde AGB des anderen Händlers');

    Livewire::actingAs(User::factory()->create())
        ->test('pages::legal-texts.edit', ['type' => LegalTextType::Terms])
        ->call('publish', $foreignVersion->id)
        ->assertForbidden();

    expect($foreignVersion->refresh()->isPublished())->toBeFalse();

    Event::assertNotDispatched(LegalTextVersionPublished::class);
});
