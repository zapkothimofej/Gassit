<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Park;
use App\Models\UnitType;
use Illuminate\Database\Eloquent\Factories\Factory;

class WaitingListFactory extends Factory
{
    public function definition(): array
    {
        return [
            'park_id'                  => Park::factory(),
            'customer_id'              => Customer::factory(),
            'unit_type_id'             => UnitType::factory(),
            'priority_score'           => $this->faker->numberBetween(0, 100),
            'notes'                    => null,
            'notified_at'              => null,
            'converted_application_id' => null,
        ];
    }
}
