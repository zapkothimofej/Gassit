<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SentEmailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id'     => Customer::factory(),
            'recipient_email' => fake()->email(),
            'subject'         => fake()->sentence(),
            'body_html'       => '<p>' . fake()->paragraph() . '</p>',
            'template_id'     => null,
            'sent_by'         => User::factory(),
            'status'          => 'sent',
            'sent_at'         => now(),
            'error_message'   => null,
        ];
    }
}
