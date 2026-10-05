<?php

use App\Models\User;
use Livewire\Livewire;

test('a merchant can create an api token and sees it once', function () {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->set('name', 'Agentur XY')
        ->call('createToken')
        ->assertHasNoErrors();

    expect($user->tokens()->sole()->name)->toBe('Agentur XY')
        ->and($component->get('plainTextToken'))->toBeString()->not->toBeEmpty();
});

test('the created token grants access to the api', function () {
    $user = User::factory()->create();

    $token = Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->set('name', 'Partner')
        ->call('createToken')
        ->get('plainTextToken');

    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/legal-texts')->assertOk();
});

test('a merchant can revoke a token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Partner')->accessToken;

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->call('revokeToken', $token->id);

    expect($user->tokens()->count())->toBe(0);
});
