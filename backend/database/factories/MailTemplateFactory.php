<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MailTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'park_id'        => null,
            'name'           => fake()->words(3, true),
            'subject'        => 'Hello {customer_name}',
            'body_html'      => '<p>Dear {customer_name},</p><p>' . fake()->paragraph() . '</p>',
            'template_type'  => fake()->randomElement(['welcome', 'invoice', 'dunning_1', 'dunning_2', 'dunning_3', 'custom']),
            'variables_json' => ['customer_name', 'park_name'],
            'active'         => true,
        ];
    }
}
