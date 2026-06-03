<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement(['created', 'updated', 'deleted', 'status_changed']),
            // Default subject is a project; override with forSubject() below.
            'subject_type' => Project::class,
            'subject_id' => Project::factory(),
            'changes' => ['before' => ['status' => 'draft'], 'after' => ['status' => 'submitted']],
        ];
    }

    /**
     * Record the log against a specific subject model.
     */
    public function forSubject(Model $subject): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
        ]);
    }
}
