<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DamageReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id'        => Unit::factory(),
            'contract_id'    => null,
            'reported_by'    => User::factory(),
            'description'    => fake()->sentence(),
            'estimated_cost' => fake()->randomFloat(2, 50, 5000),
            'actual_cost'    => null,
            'status'         => 'reported',
            'assigned_vendor_id' => null,
            'resolved_at'    => null,
        ];
    }
}
