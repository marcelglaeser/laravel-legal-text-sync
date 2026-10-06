<?php

use App\Enums\DeliveryStatus;
use App\Jobs\DeliverLegalTextVersion;
use App\Models\Delivery;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('shows the deliveries of the merchant and polls for updates', function () {
    $user = User::factory()->create();
    Delivery::factory()->for(Shop::factory()->for($user)->state(['name' => 'Mein Shop']))->failed()->create();
    Delivery::factory()->for(Shop::factory()->state(['name' => 'Fremder Shop']))->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Mein Shop')
        ->assertSee('Fehlgeschlagen')
        ->assertDontSee('Fremder Shop')
        ->assertSeeHtml('wire:poll.5s');
});

test('a failed delivery can be retried with one click', function () {
    Queue::fake();

    $user = User::factory()->create();
    $delivery = Delivery::factory()->for(Shop::factory()->for($user))->failed()->create();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->call('retry', $delivery->id)
        ->assertOk();

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Pending);

    Queue::assertPushed(DeliverLegalTextVersion::class, fn ($job) => $job->delivery->is($delivery));
});

test('a merchant cannot retry deliveries of another merchant', function () {
    Queue::fake();

    $foreignDelivery = Delivery::factory()->failed()->create();

    Livewire::actingAs(User::factory()->create())
        ->test('pages::dashboard')
        ->call('retry', $foreignDelivery->id)
        ->assertForbidden();

    expect($foreignDelivery->refresh()->status)->toBe(DeliveryStatus::Failed);

    Queue::assertNothingPushed();
});
