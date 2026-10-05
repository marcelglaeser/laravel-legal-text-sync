<?php

use App\Enums\ShopType;
use App\Models\Shop;
use App\Models\User;
use Livewire\Livewire;

test('a merchant can add a shop', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::shops.index')
        ->call('create')
        ->set('form.name', 'Mein JTL-Shop')
        ->set('form.type', ShopType::Jtl->value)
        ->set('form.endpointUrl', 'https://93.184.215.14')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->shops()->sole())
        ->name->toBe('Mein JTL-Shop')
        ->type->toBe(ShopType::Jtl)
        ->endpoint_url->toBe('https://93.184.215.14')
        ->secret->toHaveLength(40);
});

test('the built-in mock shop can be used as endpoint', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::shops.index')
        ->call('create')
        ->set('form.name', 'Demo')
        ->set('form.useMockEndpoint', true)
        ->call('save')
        ->assertHasNoErrors();

    $shop = $user->shops()->sole();

    expect($shop->endpoint_url)->toBe(route('mock-shop', $shop));
});

test('validates the shop form', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::shops.index')
        ->call('create')
        ->set('form.endpointUrl', 'not-a-url')
        ->set('form.secret', 'short')
        ->call('save')
        ->assertHasErrors(['form.name', 'form.endpointUrl', 'form.secret']);
});

test('rejects endpoints in internal networks', function (string $url) {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::shops.index')
        ->call('create')
        ->set('form.name', 'Böser Shop')
        ->set('form.endpointUrl', $url)
        ->call('save')
        ->assertHasErrors(['form.endpointUrl']);

    expect(Shop::count())->toBe(0);
})->with([
    'http://127.0.0.1:6379',
    'http://localhost/api/mock-shop/1',
    'http://169.254.169.254/latest/meta-data',
    'http://10.0.0.5/hook',
]);

test('editing keeps the secret when left empty', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->for($user)->create(['secret' => str_repeat('s', 40)]);

    Livewire::actingAs($user)
        ->test('pages::shops.index')
        ->call('edit', $shop->id)
        ->assertSet('form.secret', '')
        ->set('form.name', 'Umbenannt')
        ->call('save')
        ->assertHasNoErrors();

    expect($shop->refresh())
        ->name->toBe('Umbenannt')
        ->secret->toBe(str_repeat('s', 40));
});

test('a merchant can delete a shop', function () {
    $user = User::factory()->create();
    $shop = Shop::factory()->for($user)->create();

    Livewire::actingAs($user)->test('pages::shops.index')->call('delete', $shop->id);

    expect(Shop::whereKey($shop->id)->exists())->toBeFalse();
});

test('a merchant cannot edit or delete shops of another merchant', function (string $action) {
    $foreignShop = Shop::factory()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::shops.index')
        ->call($action, $foreignShop->id)
        ->assertForbidden();

    expect(Shop::whereKey($foreignShop->id)->exists())->toBeTrue();
})->with(['edit', 'delete']);
