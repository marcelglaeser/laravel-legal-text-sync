<?php

namespace Database\Factories;

use App\Models\MerchantProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MerchantProfile>
 */
class MerchantProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_name' => fake()->company(),
            'representative' => fake()->name(),
            'street' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'city' => fake()->city(),
            'email' => fake()->companyEmail(),
            'vat_id' => null,
            'requires_approval' => false,
        ];
    }

    public function requiresApproval(): static
    {
        return $this->state(['requires_approval' => true]);
    }
}
