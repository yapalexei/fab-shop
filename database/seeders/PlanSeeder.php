<?php

namespace Database\Seeders;

use App\Enums\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the three subscription tiers and which features each includes.
     *
     * Matches the tier x feature matrix in docs/product-desc.md. The "free"
     * plan has no permanent features - a shop's access during its 14-day
     * trial comes from Shop::onTrial(), not from being on this plan.
     */
    public function run(): void
    {
        // Placeholder monthly prices - no real pricing has been set yet, so
        // these are round numbers standing in until the business decides
        // actual figures. Feeds the platform admin dashboard's potential
        // revenue number; not tied to any payment processor.
        $plans = [
            'free' => [
                'name' => 'Free',
                'user_limit' => 1,
                'monthly_price_cents' => 0,
                'features' => [],
            ],
            'start-up' => [
                'name' => 'Start-up',
                'user_limit' => 5,
                'monthly_price_cents' => 4900,
                'features' => [Feature::Customers, Feature::QuoteBuilder, Feature::JobBoard],
            ],
            'pro' => [
                'name' => 'Pro',
                'user_limit' => 20,
                'monthly_price_cents' => 9900,
                'features' => Feature::cases(),
            ],
        ];

        foreach ($plans as $key => $attributes) {
            $plan = Plan::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $attributes['name'],
                    'user_limit' => $attributes['user_limit'],
                    'monthly_price_cents' => $attributes['monthly_price_cents'],
                ],
            );

            $plan->syncFeatures($attributes['features']);
        }
    }
}
