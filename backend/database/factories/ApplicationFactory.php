<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Park;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'park_id'            => Park::factory(),
            'customer_id'        => Customer::factory(),
            'unit_type_id'       => UnitType::factory(),
            'unit_id'            => null,
            'desired_start_date' => fake()->dateTimeBetween('+1 month', '+6 months')->format('Y-m-d'),
            'status'             => 'new',
            'assigned_to'        => null,
            'credit_check_path'  => null,
            'notes'              => null,
            'source'             => 'walk_in',
        ];
    }
}
