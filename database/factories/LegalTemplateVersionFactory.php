<?php

namespace Database\Factories;

use App\Enums\LegalTextType;
use App\Models\LegalTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalTemplateVersion>
 */
class LegalTemplateVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => LegalTextType::Imprint,
            'version' => fn (array $attributes) => (int) LegalTemplateVersion::query()->where('type', $attributes['type'])->max('version') + 1,
            'content' => "Angaben gemäß § 5 DDG\n{{ company_name }}\n{{ street }}\n{{ postal_code }} {{ city }}\nVertreten durch: {{ representative }}\nE-Mail: {{ email }}",
        ];
    }

    public function published(): static
    {
        return $this->state(['published_at' => now()]);
    }
}
