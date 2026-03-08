<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerDocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'   => Customer::factory(),
            'document_type' => 'id_card',
            'path'          => 'customers/1/documents/test.pdf',
            'filename'      => 'test.pdf',
            'uploaded_by'   => User::factory(),
        ];
    }
}
