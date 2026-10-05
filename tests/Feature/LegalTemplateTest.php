<?php

use App\Enums\LegalTextType;
use App\Events\LegalTemplateVersionPublished;
use App\Jobs\GenerateLegalText;
use App\Listeners\GenerateLegalTextsForMerchants;
use App\Models\LegalTemplateVersion;
use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

test('drafts are numbered per type and unchanged content creates no new version', function () {
    $first = LegalTemplateVersion::draft(LegalTextType::Terms, 'AGB v1');
    $same = LegalTemplateVersion::draft(LegalTextType::Terms, 'AGB v1');
    $second = LegalTemplateVersion::draft(LegalTextType::Terms, 'AGB v2');
    $imprint = LegalTemplateVersion::draft(LegalTextType::Imprint, 'Impressum');

    expect($same->is($first))->toBeTrue()
        ->and($second->version)->toBe(2)
        ->and($imprint->version)->toBe(1);
});

test('publishing a template dispatches the event exactly once', function () {
    Event::fake([LegalTemplateVersionPublished::class]);

    $template = LegalTemplateVersion::draft(LegalTextType::Terms, 'AGB');

    expect($template->publish())->toBeTrue()
        ->and($template->publish())->toBeFalse();

    Event::assertDispatchedTimes(LegalTemplateVersionPublished::class, 1);
});

test('the current template is the latest published one', function () {
    Event::fake([LegalTemplateVersionPublished::class]);

    LegalTemplateVersion::draft(LegalTextType::Terms, 'v1')->publish();
    $this->travel(1)->minute();
    LegalTemplateVersion::draft(LegalTextType::Terms, 'v2')->publish();
    LegalTemplateVersion::draft(LegalTextType::Terms, 'v3 draft');

    expect(LegalTemplateVersion::current(LegalTextType::Terms)?->content)->toBe('v2')
        ->and(LegalTemplateVersion::current(LegalTextType::Privacy))->toBeNull();
});

test('renders the merchant data into the placeholders', function () {
    $profile = MerchantProfile::factory()->make([
        'company_name' => 'Musterhändler GmbH',
        'city' => 'Musterstadt',
        'vat_id' => null,
    ]);
    $template = LegalTemplateVersion::factory()->make([
        'content' => '{{ company_name }} aus {{ city }}, USt-IdNr.: {{ vat_id }}',
    ]);

    expect($template->render($profile))->toBe('Musterhändler GmbH aus Musterstadt, USt-IdNr.: ');
});

test('publishing a template queues text generation for every merchant', function () {
    Queue::fake();

    MerchantProfile::factory()->count(3)->create();
    User::factory()->create();
    $template = publishedTemplate();

    (new GenerateLegalTextsForMerchants)->handle(new LegalTemplateVersionPublished($template));

    Queue::assertPushed(GenerateLegalText::class, 3);
});

test('the generation listener is registered', function () {
    Event::fake();

    Event::assertListening(LegalTemplateVersionPublished::class, GenerateLegalTextsForMerchants::class);
});

test('generation jobs for outdated templates are skipped', function () {
    $profile = MerchantProfile::factory()->create();
    $outdated = publishedTemplate(LegalTextType::Terms, 'AGB alt');
    $this->travel(1)->minute();
    publishedTemplate(LegalTextType::Terms, 'AGB neu');

    GenerateLegalText::dispatchSync($profile, $outdated);

    expect($profile->user->legalText(LegalTextType::Terms)->versions()->count())->toBe(0);
});
