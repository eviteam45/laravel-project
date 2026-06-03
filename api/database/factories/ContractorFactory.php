<?php

namespace Database\Factories;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contractor>
 */
class ContractorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->contractor(),
            'company_name' => fake()->company(),
            'license_no' => fake()->bothify('LIC-#####'),
            'phone' => fake()->phoneNumber(),
            'region' => fake()->randomElement(['North', 'South', 'East', 'West', 'Central']),
            'status' => fake()->randomElement(['active', 'active', 'pending', 'inactive']),
        ];
    }
}
