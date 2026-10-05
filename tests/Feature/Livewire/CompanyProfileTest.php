<?php

use App\Enums\LegalTextType;
use App\Models\User;
use Livewire\Livewire;

test('saving the company profile generates the legal texts', function () {
    publishedTemplate(LegalTextType::Imprint, '{{ company_name }}, {{ street }}, {{ postal_code }} {{ city }}');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::company-profile')
        ->set('form.companyName', 'Musterhändler GmbH')
        ->set('form.representative', 'Max Mustermann')
        ->set('form.street', 'Musterstraße 1')
        ->set('form.postalCode', '12345')
        ->set('form.city', 'Musterstadt')
        ->set('form.email', 'info@musterhaendler.de')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()?->merchantProfile?->requires_approval)->toBeFalse()
        ->and($user->legalText(LegalTextType::Imprint)->currentVersion?->content)
        ->toBe('Musterhändler GmbH, Musterstraße 1, 12345 Musterstadt');
});

test('validates the company profile', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::company-profile')
        ->set('form.email', 'not-an-email')
        ->call('save')
        ->assertHasErrors(['form.companyName', 'form.representative', 'form.street', 'form.postalCode', 'form.city', 'form.email']);
});

test('the approval setting is stored with the profile', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::company-profile')
        ->set('form.companyName', 'Musterhändler GmbH')
        ->set('form.representative', 'Max Mustermann')
        ->set('form.street', 'Musterstraße 1')
        ->set('form.postalCode', '12345')
        ->set('form.city', 'Musterstadt')
        ->set('form.email', 'info@musterhaendler.de')
        ->set('form.requiresApproval', true)
        ->call('save');

    expect($user->fresh()?->merchantProfile?->requires_approval)->toBeTrue();
});
