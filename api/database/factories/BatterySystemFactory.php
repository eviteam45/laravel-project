<?php

namespace Database\Factories;

use App\Models\BatterySystem;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatterySystem>
 */
class BatterySystemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'oem' => fake()->randomElement(['Tesla', 'LG', 'Enphase', 'SonnenBatterie', 'BYD']),
            'model' => fake()->bothify('Model-??##'),
            'quantity' => fake()->numberBetween(1, 4),
            'usable_capacity_kwh' => fake()->randomFloat(2, 5, 20),
        ];
    }
}
