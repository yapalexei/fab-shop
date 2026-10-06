<?php

namespace Database\Factories;

use App\Models\AiRequest;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiRequest>
 */
class AiRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'user_id' => null,
            'feature' => 'quote-intake',
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5-5',
            'input_tokens' => 1000,
            'output_tokens' => 200,
            'latency_ms' => 1500,
            'status' => 'ok',
        ];
    }
}
