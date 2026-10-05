<?php

namespace Database\Factories;

use App\Models\LegalTemplateVersion;
use App\Models\LegalText;
use App\Models\LegalTextVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalTextVersion>
 */
class LegalTextVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'legal_text_id' => LegalText::factory(),
            'legal_template_version_id' => LegalTemplateVersion::factory()->published(),
            'version' => 1,
            'content' => fake()->paragraphs(3, true),
        ];
    }

    public function published(): static
    {
        return $this->state(['published_at' => now()]);
    }
}
