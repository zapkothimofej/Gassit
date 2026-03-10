<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Park;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $subtotal    = fake()->randomFloat(2, 50, 1000);
        $taxRate     = 19.00;
        $taxAmount   = round($subtotal * $taxRate / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        return [
            'contract_id'    => null,
            'customer_id'    => Customer::factory(),
            'park_id'        => Park::factory(),
            'invoice_number' => fake()->unique()->numerify('TEST-####-###'),
            'issue_date'     => now()->format('Y-m-d'),
            'due_date'       => now()->addDays(14)->format('Y-m-d'),
            'subtotal'       => $subtotal,
            'tax_rate'       => $taxRate,
            'tax_amount'     => $taxAmount,
            'total_amount'   => $totalAmount,
            'status'         => 'draft',
        ];
    }
}
