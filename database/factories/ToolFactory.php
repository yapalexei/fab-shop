<?php

namespace Database\Factories;

use App\Enums\CuttableMaterial;
use App\Models\Shop;
use App\Models\Tool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tool>
 */
class ToolFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->randomElement(['1/4in drill bit', '1/2in hole saw', '4.5in cut-off wheel', '3/8-16 tap']),
            'cuts' => [fake()->randomElement(CuttableMaterial::cases())->value],
            'cost' => fake()->randomFloat(2, 2, 60),
            'unit' => 'each',
            'notes' => null,
        ];
    }
}
