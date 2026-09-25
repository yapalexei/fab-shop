<?php

namespace Database\Factories;

use App\Enums\JobStageRole;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Quote;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Job>
 */
class JobFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->for($shop)->create();

        // Shop::created already seeded the shop's job_stages (JobStages::
        // seedDefaults) by the time this runs.
        $notStarted = JobStage::query()->where('shop_id', $shop->id)->where('role', JobStageRole::NotStarted)->firstOrFail();

        return [
            'shop_id' => $shop->id,
            'quote_id' => Quote::factory()->for($shop)->create(['customer_id' => $customer->id])->id,
            'customer_id' => $customer->id,
            'job_number' => 'J-'.$this->faker->unique()->numerify('####'),
            'job_stage_id' => $notStarted->id,
        ];
    }
}
