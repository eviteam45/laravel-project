<?php

namespace Database\Factories;

use App\Models\ApplicationStep;
use App\Models\IncentiveApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationStep>
 */
class ApplicationStepFactory extends Factory
{
    public function definition(): array
    {
        $completed = fake()->boolean(60);

        return [
            'application_id' => IncentiveApplication::factory(),
            'step_key' => fake()->randomElement(['eligibility', 'documents', 'review', 'payment']),
            'data' => ['notes' => fake()->sentence(), 'valid' => fake()->boolean()],
            'completed_at' => $completed ? fake()->dateTimeBetween('-3 months') : null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => fake()->dateTimeBetween('-3 months'),
        ]);
    }
}
