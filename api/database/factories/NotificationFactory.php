<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement([
                'application_submitted',
                'application_approved',
                'payment_scheduled',
                'document_requested',
            ]),
            'data' => ['message' => fake()->sentence()],
            'read_at' => fake()->optional()->dateTimeBetween('-1 month'),
        ];
    }

    public function unread(): static
    {
        return $this->state(fn (array $attributes) => ['read_at' => null]);
    }
}
