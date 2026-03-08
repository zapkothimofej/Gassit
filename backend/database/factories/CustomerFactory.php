<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type'       => 'private',
            'first_name' => fake()->firstName(),
            'last_name'  => fake()->lastName(),
            'email'      => fake()->unique()->safeEmail(),
            'phone'      => fake()->phoneNumber(),
            'address'    => fake()->streetAddress(),
            'city'       => fake()->city(),
            'zip'        => fake()->postcode(),
            'country'    => 'DE',
            'status'     => 'new',
        ];
    }
}
