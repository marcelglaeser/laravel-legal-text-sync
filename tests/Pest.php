<?php

use App\Enums\LegalTextType;
use App\Models\LegalTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function publishedTemplate(LegalTextType $type = LegalTextType::Imprint, string $content = 'Impressum der {{ company_name }}'): LegalTemplateVersion
{
    return LegalTemplateVersion::factory()->published()->create(['type' => $type, 'content' => $content]);
}
