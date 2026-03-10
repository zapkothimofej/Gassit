<?php

namespace Database\Factories;

use App\Models\Park;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Unit>
 */
class UnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'park_id'      => Park::factory(),
            'unit_type_id' => UnitType::factory(),
            'unit_number'  => fake()->numerify('U-###'),
            'floor'        => fake()->optional()->numberBetween(0, 5),
            'building'     => fake()->optional()->word(),
            'size_m2'      => fake()->randomFloat(2, 10, 100),
            'status'       => 'free',
        ];
    }
}
