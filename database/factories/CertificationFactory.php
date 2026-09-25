<?php

namespace Database\Factories;

use App\Models\Certification;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Certification>
 */
class CertificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'user_id' => User::factory(),
            'procedure_id' => null,
            'cert_number' => fake()->bothify('CERT-####'),
            'issuing_body' => fake()->randomElement(['AWS', 'CWB', 'OSHA']),
            'issued_at' => now()->subYear(),
            'expires_at' => now()->addYear(),
            'notes' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state([
            'issued_at' => now()->subYears(3),
            'expires_at' => now()->subYear(),
        ]);
    }
}
