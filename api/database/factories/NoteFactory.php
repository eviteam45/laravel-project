<?php

namespace Database\Factories;

use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Default owner is a project; override with forOwner() below.
            'notable_type' => Project::class,
            'notable_id' => Project::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
        ];
    }

    /**
     * Attach the note to a specific model (project, application, ...).
     */
    public function forOwner(Model $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'notable_type' => $owner::class,
            'notable_id' => $owner->getKey(),
        ]);
    }
}
