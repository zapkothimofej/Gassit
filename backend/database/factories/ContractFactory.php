<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'        => Customer::factory(),
            'unit_id'            => Unit::factory(),
            'start_date'         => fake()->dateTimeBetween('-2 years', '-1 year')->format('Y-m-d'),
            'end_date'           => fake()->optional()->dateTimeBetween('-1 year', '+1 year')?->format('Y-m-d'),
            'notice_period_days' => 30,
            'rent_amount'        => fake()->randomFloat(2, 100, 1000),
            'deposit_amount'     => fake()->randomFloat(2, 200, 2000),
            'status'             => 'active',
        ];
    }
}
