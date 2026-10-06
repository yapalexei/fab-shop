<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopAiSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopAiSetting>
 */
class ShopAiSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'source' => 'byok',
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5-5',
            'api_key' => 'sk-test-'.fake()->unique()->bothify('????????'),
        ];
    }
}
