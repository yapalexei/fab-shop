<?php

namespace Database\Factories;

use App\Models\Procedure;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Procedure>
 */
class ProcedureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            // Numbered rather than a small fixed pool of names, since a
            // shop-scoped case-insensitive unique index backs this - a
            // small pool would collide across factory calls in the same test.
            'name' => 'Procedure '.fake()->unique()->numberBetween(1, 1000000),
            'code' => null,
            'notes' => null,
        ];
    }
}
