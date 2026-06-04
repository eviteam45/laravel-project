<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [

            'notable_type' => Project::class,
            'notable_id' => Project::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    public function forOwner(Model $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'notable_type' => $owner::class,
            'notable_id' => $owner->getKey(),
        ]);
    }
}
