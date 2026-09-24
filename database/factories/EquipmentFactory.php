<?php

namespace Database\Factories;

use App\Enums\BillingMethod;
use App\Models\Equipment;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Equipment>
 */
class EquipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->randomElement([
                'Plasma cutter', 'MIG welder', 'TIG welder', 'Press brake', 'Bandsaw', 'CNC router',
            ]),
            'billing_method' => fake()->randomElement(BillingMethod::cases())->value,
            'rate' => fake()->randomFloat(2, 15, 150),
            'notes' => null,
        ];
    }
}
