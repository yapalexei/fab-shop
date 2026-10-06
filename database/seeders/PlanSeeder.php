<?php

namespace Database\Seeders;

use App\Enums\Feature;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the four subscription tiers and which features each includes.
     *
     * Matches the tier x feature matrix in docs/product-desc.md. The "free"
     * plan has no permanent features - a shop's access during its 14-day
     * trial comes from Shop::onTrial(), not from being on this plan.
     */
    public function run(): void
    {
        // Launch prices (hypotheses to test, not market-validated). Feeds
        // the platform admin dashboard's potential revenue number; not tied
        // to any payment processor.
        $plans = [
            // Not marketed as a tier: what a shop falls back to when its
            // trial ends without picking a plan.
            'free' => [
                'name' => 'Free',
                'user_limit' => 1,
                'monthly_price_cents' => 0,
                'features' => [],
            ],
            // Quoting for a one-person shop. Emailing the quote is here
            // because sending it is where the product proves itself on day
            // one. No job board: a solo operator already knows their own
            // job's status.
            'solo' => [
                'name' => 'Solo',
                'user_limit' => 1,
                'monthly_price_cents' => 2900,
                'features' => [Feature::Customers, Feature::QuoteBuilder, Feature::QuoteEmailing],
            ],
            // Key stays 'start-up' (referenced by code, tests and existing
            // shops); only the display name changed - these are established
            // trades businesses, not start-ups.
            'start-up' => [
                'name' => 'Shop',
                'user_limit' => 4,
                'monthly_price_cents' => 5900,
                'extra_seat_price_cents' => 900,
                'features' => [
                    Feature::Customers, Feature::QuoteBuilder, Feature::QuoteEmailing,
                    Feature::JobBoard, Feature::QuoteHistory, Feature::AiAssist,
                ],
            ],
            // Margin and compliance: material/scrap tracking, qualification
            // records and AI agent access, plus whatever ships later.
            'pro' => [
                'name' => 'Pro',
                'user_limit' => 10,
                'monthly_price_cents' => 11900,
                'extra_seat_price_cents' => 900,
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
                    'extra_seat_price_cents' => $attributes['extra_seat_price_cents'] ?? null,
                ],
            );

            $plan->syncFeatures($attributes['features']);
        }
    }
}
