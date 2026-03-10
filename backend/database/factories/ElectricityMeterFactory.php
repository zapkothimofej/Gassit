<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElectricityMeterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id'      => Unit::factory(),
            'meter_number' => fake()->numerify('METER-####'),
            'meter_type'   => fake()->randomElement(['main', 'sub']),
            'active'       => true,
            'installed_at' => fake()->date(),
        ];
    }
}
