<?php

namespace Database\Factories;

use App\Models\Park;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElectricityPricingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'park_id'       => Park::factory(),
            'price_per_kwh' => fake()->randomFloat(6, 0.1, 0.5),
            'valid_from'    => now()->subMonths(3)->format('Y-m-d'),
            'valid_to'      => null,
        ];
    }
}
