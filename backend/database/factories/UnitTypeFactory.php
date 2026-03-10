<?php

namespace Database\Factories;

use App\Models\Park;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UnitType>
 */
class UnitTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'park_id'        => Park::factory(),
            'name'           => fake()->words(2, true) . ' Type',
            'description'    => fake()->optional()->sentence(),
            'base_rent'      => fake()->randomFloat(2, 100, 1000),
            'deposit_amount' => fake()->randomFloat(2, 200, 2000),
            'size_m2'        => fake()->randomFloat(2, 10, 100),
            'floor_plan_path' => null,
        ];
    }
}
