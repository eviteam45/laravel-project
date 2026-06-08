<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\IncentiveApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncentivePaymentFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement(PaymentStatus::values());

        return [
            'application_id' => IncentiveApplication::factory(),
            'amount' => fake()->randomFloat(2, 500, 10000),
            'status' => $status,
            'scheduled_for' => in_array($status, ['scheduled', 'paid'], true)
                ? fake()->dateTimeBetween('-2 months', '+1 month')
                : null,
            'paid_at' => $status === 'paid' ? fake()->dateTimeBetween('-2 months') : null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => fake()->dateTimeBetween('-2 months'),
        ]);
    }
}
