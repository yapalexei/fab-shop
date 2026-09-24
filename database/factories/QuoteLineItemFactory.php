<?php

namespace Database\Factories;

use App\Enums\LineItemType;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLineItem>
 */
class QuoteLineItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'type' => fake()->randomElement(LineItemType::cases())->value,
            'description' => fake()->words(3, true),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit_cost' => fake()->randomFloat(2, 10, 200),
            'markup_percent' => fake()->randomElement([0, 15, 25, 40]),
            'override_total' => null,
            'sort_order' => 0,
        ];
    }
}
