<?php

namespace Database\Factories;

use App\Models\Park;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'park_id'      => Park::factory(),
            'name'         => fake()->company(),
            'contact_name' => fake()->name(),
            'phone'        => fake()->phoneNumber(),
            'email'        => fake()->unique()->safeEmail(),
            'specialty'    => fake()->randomElement(['plumbing', 'electrical', 'painting', 'roofing']),
            'hourly_rate'  => fake()->randomFloat(2, 30, 150),
            'active'       => true,
        ];
    }
}
