<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class DunningRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id'    => Invoice::factory(),
            'customer_id'   => Customer::factory(),
            'level'         => fake()->numberBetween(1, 3),
            'sent_at'       => now(),
            'fee_amount'    => fake()->randomFloat(2, 5, 50),
            'template_used' => null,
        ];
    }
}
