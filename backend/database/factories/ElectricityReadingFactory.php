<?php

namespace Database\Factories;

use App\Models\ElectricityMeter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ElectricityReadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'meter_id'      => ElectricityMeter::factory(),
            'reading_date'  => fake()->date(),
            'reading_value' => fake()->randomFloat(4, 100, 9999),
            'photo_path'    => null,
            'recorded_by'   => User::factory(),
            'consumption'   => fake()->randomFloat(4, 0, 500),
        ];
    }
}
