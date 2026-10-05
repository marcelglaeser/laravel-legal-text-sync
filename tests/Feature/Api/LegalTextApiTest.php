<?php

use App\Enums\LegalTextType;
use App\Models\LegalText;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function publish(LegalText $legalText, string $content): void
{
    $legalText->createVersion($content, publishedTemplate())->forceFill(['published_at' => now()])->save();
}

test('requires a sanctum token', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    '/api/legal-texts',
    '/api/legal-texts/imprint',
    '/api/legal-texts/imprint/versions',
]);

test('lists the current published legal texts of the merchant', function () {
    $user = User::factory()->create();
    publish($user->legalText(LegalTextType::Imprint), 'Impressum v1');
    $this->travel(1)->minute();
    publish($user->legalText(LegalTextType::Imprint), 'Impressum v2');
    $user->legalText(LegalTextType::Terms)->createVersion('AGB draft', publishedTemplate());
    publish(User::factory()->create()->legalText(LegalTextType::Privacy), 'Foreign');

    Sanctum::actingAs($user);

    $this->getJson('/api/legal-texts')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'imprint')
        ->assertJsonPath('data.0.title', 'Impressum')
        ->assertJsonPath('data.0.current_version.version', 2)
        ->assertJsonPath('data.0.current_version.content', 'Impressum v2');
});

test('shows a single current legal text', function () {
    $user = User::factory()->create();
    publish($user->legalText(LegalTextType::Withdrawal), 'Widerruf');

    Sanctum::actingAs($user);

    $this->getJson('/api/legal-texts/withdrawal')
        ->assertOk()
        ->assertJsonPath('data.current_version.content', 'Widerruf');
});

test('returns 404 for unpublished or unknown legal text types', function (string $type) {
    $user = User::factory()->create();
    $user->legalText(LegalTextType::Terms)->createVersion('AGB draft', publishedTemplate());

    Sanctum::actingAs($user);

    $this->getJson("/api/legal-texts/{$type}")->assertNotFound();
})->with(['terms', 'cookie-policy']);

test('returns the published version history, newest first', function () {
    $user = User::factory()->create();
    $legalText = $user->legalText(LegalTextType::Terms);
    publish($legalText, 'AGB v1');
    publish($legalText, 'AGB v2');
    $legalText->createVersion('AGB v3 draft', publishedTemplate());

    Sanctum::actingAs($user);

    $this->getJson('/api/legal-texts/terms/versions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.version', 2)
        ->assertJsonPath('data.1.version', 1)
        ->assertJsonPath('meta.total', 2);
});
