<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Park>
 */
class ParkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Park',
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'zip' => fake()->postcode(),
            'country' => 'DE',
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'bank_iban' => 'DE' . fake()->numerify('####################'),
            'bank_bic' => fake()->lexify('????????'),
            'bank_owner' => fake()->company(),
            'language' => 'de',
        ];
    }
}
