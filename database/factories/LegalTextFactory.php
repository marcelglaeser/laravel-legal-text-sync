<?php

namespace Database\Factories;

use App\Enums\LegalTextType;
use App\Models\LegalText;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalText>
 */
class LegalTextFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => LegalTextType::Imprint,
        ];
    }
}
