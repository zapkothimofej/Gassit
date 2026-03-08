<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id'         => Invoice::factory(),
            'amount'             => fake()->randomFloat(2, 50, 1000),
            'currency'           => 'EUR',
            'payment_method'     => 'mollie',
            'mollie_payment_id'  => 'tr_' . fake()->unique()->lexify('??????????'),
            'mollie_checkout_url'=> 'https://sandbox.mollie.com/checkout/select-method/test',
            'status'             => 'pending',
            'retry_count'        => 0,
        ];
    }
}
