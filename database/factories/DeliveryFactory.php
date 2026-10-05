<?php

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\LegalTextVersion;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'legal_text_version_id' => LegalTextVersion::factory()->published(),
            'status' => DeliveryStatus::Pending,
        ];
    }

    public function failed(): static
    {
        return $this->state([
            'status' => DeliveryStatus::Failed,
            'attempts' => 5,
            'last_error' => 'HTTP request returned status code 500',
        ]);
    }
}
