<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\IncentiveApplication;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Default owner is a project; override with forOwner() below.
            'documentable_type' => Project::class,
            'documentable_id' => Project::factory(),
            'type' => fake()->randomElement(['contract', 'invoice', 'photo', 'spec_sheet', 'permit']),
            'file_path' => 'documents/'.fake()->uuid().'.pdf',
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * Attach the document to a specific project or application.
     */
    public function forOwner(Model $owner): static
    {
        return $this->state(fn (array $attributes) => [
            'documentable_type' => $owner::class,
            'documentable_id' => $owner->getKey(),
        ]);
    }

    public function forApplication(): static
    {
        return $this->state(fn (array $attributes) => [
            'documentable_type' => IncentiveApplication::class,
            'documentable_id' => IncentiveApplication::factory(),
        ]);
    }
}
