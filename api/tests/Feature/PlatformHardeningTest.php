<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\IncentiveApplication;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function contractorOwning(Project $project): User
    {
        return $project->contractor->user;
    }

    public function test_deleting_an_application_removes_its_uploaded_files(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create();
        $application = IncentiveApplication::factory()->for($project)->create(['status' => 'in_progress']);

        $path = UploadedFile::fake()->create('proof.pdf', 10)->store('documents/apps', 'local');
        Document::create([
            'documentable_type' => IncentiveApplication::class,
            'documentable_id' => $application->id,
            'type' => 'proof',
            'file_path' => $path,
        ]);

        Storage::disk('local')->assertExists($path);

        $application->delete();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_deleting_a_project_cleans_up_application_files(): void
    {
        Storage::fake('local');

        $project = Project::factory()->create();
        $application = IncentiveApplication::factory()->for($project)->create(['status' => 'in_progress']);

        $path = UploadedFile::fake()->create('spec.pdf', 10)->store('documents/apps', 'local');
        Document::create([
            'documentable_type' => IncentiveApplication::class,
            'documentable_id' => $application->id,
            'type' => 'spec_sheet',
            'file_path' => $path,
        ]);

        $project->delete();

        Storage::disk('local')->assertMissing($path);
    }

    public function test_a_submitted_application_cannot_be_deleted(): void
    {
        $project = Project::factory()->create();
        $application = IncentiveApplication::factory()->for($project)->create(['status' => 'submitted']);

        Sanctum::actingAs($this->contractorOwning($project));

        $this->deleteJson("/api/applications/{$application->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('application');
    }

    public function test_a_project_with_an_application_under_review_cannot_be_deleted(): void
    {
        $project = Project::factory()->create();
        IncentiveApplication::factory()->for($project)->create(['status' => 'under_review']);

        Sanctum::actingAs($this->contractorOwning($project));

        $this->deleteJson("/api/projects/{$project->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('project');
    }
}
