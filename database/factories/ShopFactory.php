<?php

namespace Database\Factories;

use App\Models\PlatformSetting;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shop>
 */
class ShopFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Same as a real signup: the trial runs the platform default and the
        // shop records it as its baseline (see Shop::trialBaselineDays()).
        $trialDays = PlatformSetting::defaultTrialDays();

        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'trial_started_at' => now(),
            'trial_ends_at' => now()->addDays($trialDays),
            'trial_baseline_days' => $trialDays,
            // Factory shops are already fully set up - only the real
            // onboarding wizard (and tests of it) deal with onboarded_at
            // being null.
            'onboarded_at' => now(),
        ];
    }

    /**
     * The owner is always a member of their own shop.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Shop $shop) {
            $shop->members()->syncWithoutDetaching([$shop->owner_id]);
        });
    }
}
