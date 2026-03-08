<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Park;
use Illuminate\Database\Eloquent\Factories\Factory;

class DepositFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contract_id'      => Contract::factory(),
            'customer_id'      => Customer::factory(),
            'park_id'          => Park::factory(),
            'amount'           => fake()->randomFloat(2, 200, 2000),
            'status'           => 'pending',
            'received_at'      => null,
            'returned_at'      => null,
            'return_amount'    => null,
            'deduction_amount' => null,
            'deduction_reason' => null,
            'return_method'    => null,
            'mollie_payment_id' => null,
        ];
    }
}
