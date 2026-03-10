<?php

namespace Database\Factories;

use App\Models\Park;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'park_id'     => Park::factory(),
            'type'        => fake()->randomElement(['application', 'damage', 'ticket', 'general', 'inspection', 'renewal']),
            'title'       => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'assigned_to' => null,
            'created_by'  => User::factory(),
            'status'      => 'todo',
            'due_date'    => fake()->optional()->dateTimeBetween('now', '+30 days')?->format('Y-m-d'),
            'priority'    => fake()->randomElement(['low', 'medium', 'high', 'urgent']),
            'related_type' => null,
            'related_id'  => null,
            'completed_at' => null,
        ];
    }
}
