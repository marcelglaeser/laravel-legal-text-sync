<?php

namespace Database\Factories;

use App\Enums\ShopType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->company(),
            'type' => ShopType::GenericWebhook,
            'endpoint_url' => 'https://93.184.215.14/legal-texts',
            'secret' => Str::random(40),
        ];
    }

    public function shopify(): static
    {
        return $this->state(['type' => ShopType::Shopify]);
    }

    public function jtl(): static
    {
        return $this->state(['type' => ShopType::Jtl]);
    }
}
