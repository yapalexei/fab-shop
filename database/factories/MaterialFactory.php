<?php

namespace Database\Factories;

use App\Models\Material;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
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
                '1/4in steel plate', '16ga sheet metal', '2in angle iron',
                '1in square tube', 'stainless rod', 'aluminum flat bar',
            ]),
            'unit' => fake()->randomElement(['each', 'ft', 'lb', 'sheet']),
            'notes' => null,
        ];
    }
}
