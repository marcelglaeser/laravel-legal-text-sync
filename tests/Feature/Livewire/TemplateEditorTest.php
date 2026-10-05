<?php

use App\Enums\LegalTextType;
use App\Events\LegalTemplateVersionPublished;
use App\Models\LegalTemplateVersion;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

test('only admins can open the template area', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.templates.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->get(route('admin.templates.index'))
        ->assertOk();
});

test('an admin saves a template as a new draft', function () {
    Livewire::actingAs(User::factory()->create(['is_admin' => true]))
        ->test('pages::admin.templates.edit', ['type' => LegalTextType::Terms])
        ->set('content', 'AGB der {{ company_name }} in neuer Fassung')
        ->call('save')
        ->assertHasNoErrors();

    expect(LegalTemplateVersion::latestFor(LegalTextType::Terms))
        ->version->toBe(1)
        ->isPublished()->toBeFalse();
});

test('an admin publishes the latest draft', function () {
    Event::fake([LegalTemplateVersionPublished::class]);

    $draft = LegalTemplateVersion::draft(LegalTextType::Terms, 'AGB der {{ company_name }}');

    Livewire::actingAs(User::factory()->create(['is_admin' => true]))
        ->test('pages::admin.templates.edit', ['type' => LegalTextType::Terms])
        ->call('publish', $draft->id)
        ->assertOk();

    expect($draft->refresh()->isPublished())->toBeTrue();

    Event::assertDispatched(LegalTemplateVersionPublished::class);
});

test('superseded drafts cannot be published', function () {
    Event::fake([LegalTemplateVersionPublished::class]);

    $old = LegalTemplateVersion::draft(LegalTextType::Terms, 'AGB alt');
    LegalTemplateVersion::draft(LegalTextType::Terms, 'AGB neu');

    Livewire::actingAs(User::factory()->create(['is_admin' => true]))
        ->test('pages::admin.templates.edit', ['type' => LegalTextType::Terms])
        ->call('publish', $old->id)
        ->assertForbidden();

    Event::assertNotDispatched(LegalTemplateVersionPublished::class);
});
