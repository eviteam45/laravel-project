<?php

namespace Database\Factories;

use App\Models\IncentiveApplication;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncentiveApplicationFactory extends Factory
{
    public function definition(): array
    {
        $status = fake()->randomElement(IncentiveApplication::STATUSES);
        $preSubmit = in_array($status, ['started', 'in_progress'], true);

        return [
            'project_id' => Project::factory(),
            'status' => $status,
            'current_step' => fake()->randomElement([...IncentiveApplication::STEP_KEYS, null]),
            'submitted_at' => $preSubmit ? null : fake()->dateTimeBetween('-6 months'),
            'incentive_amount' => in_array($status, ['reserved', 'paid'], true)
                ? fake()->randomFloat(2, 500, 10000)
                : null,
        ];
    }
}
