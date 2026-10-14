<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'stripe_price_id' => null,
            'user_limit' => null,
            'monthly_price_cents' => 0,
            'extra_seat_price_cents' => null,
            // The launch tiers sort where they always have; any other key
            // goes after them.
            'position' => function (array $attributes): int {
                $index = array_search($attributes['key'], Plan::DEFAULT_ORDER, true);

                return $index === false ? 100 : $index;
            },
        ];
    }
}
