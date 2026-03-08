<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vendor_id'        => Vendor::factory(),
            'damage_report_id' => null,
            'amount'           => fake()->randomFloat(2, 50, 5000),
            'paid_at'          => null,
            'pdf_path'         => null,
        ];
    }
}
