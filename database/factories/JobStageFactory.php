<?php

namespace Database\Factories;

use App\Models\JobStage;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobStage>
 */
class JobStageFactory extends Factory
{
    protected $model = JobStage::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => ucfirst($this->faker->unique()->word()),
            // Starts well past the seeded built-ins' 0-3 range so a factory
            // stage never collides with them on (shop_id, position).
            'position' => $this->faker->unique()->numberBetween(100, 999),
            'role' => null,
        ];
    }
}
