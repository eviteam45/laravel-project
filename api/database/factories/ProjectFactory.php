<?php

namespace Database\Factories;

use App\Models\Contractor;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->streetName().' Storage Project',
            'contractor_id' => Contractor::factory(),
            'customer_id' => Customer::factory(),
            'status' => fake()->randomElement(Project::STATUSES),
            'address' => fake()->address(),
            'capacity_kw' => fake()->randomFloat(2, 3, 50),
            'install_date' => fake()->optional()->dateTimeBetween('-1 year', '+1 month'),
        ];
    }
}
