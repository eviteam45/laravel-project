<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Document;
use App\Models\IncentiveApplication;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function contractorOwning(Project $project): User
    {
        return $project->contractor->user;
    }

    public function test_soft_deleting_an_application_retains_its_uploaded_files(): void
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

        $application->delete();

        // Soft delete keeps the row recoverable, so the files must survive.
        $this->assertSoftDeleted('incentive_applications', ['id' => $application->id]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_soft_deleting_a_project_retains_application_files(): void
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

        $this->assertSoftDeleted('projects', ['id' => $project->id]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_an_illegal_status_is_rejected_by_the_database(): void
    {
        $project = Project::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('projects')->where('id', $project->id)->update(['status' => 'not-a-real-status']);
    }

    public function test_a_project_cannot_have_two_active_applications(): void
    {
        $project = Project::factory()->create();
        IncentiveApplication::factory()->for($project)->create();

        $this->expectException(QueryException::class);
        IncentiveApplication::factory()->for($project)->create();
    }

    public function test_a_new_application_is_allowed_after_the_previous_is_soft_deleted(): void
    {
        $project = Project::factory()->create();
        $first = IncentiveApplication::factory()->for($project)->create();

        $first->delete();

        $second = IncentiveApplication::factory()->for($project)->create();
        $this->assertNotNull($second->id);
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_duplicate_subject_notifications_are_blocked_by_a_unique_index(): void
    {
        $user = User::factory()->create();
        Notification::create(['user_id' => $user->id, 'type' => 'application_reserved', 'data' => ['application_id' => 5]]);

        $this->expectException(QueryException::class);
        Notification::create(['user_id' => $user->id, 'type' => 'application_reserved', 'data' => ['application_id' => 5]]);
    }

    public function test_changing_role_removes_the_previous_role_profile(): void
    {
        $user = User::factory()->contractor()->create();
        Contractor::factory()->create(['user_id' => $user->id]);

        $user->changeRole('customer');

        $this->assertDatabaseMissing('contractors', ['user_id' => $user->id]);
        $this->assertDatabaseHas('customers', ['user_id' => $user->id]);
    }

    public function test_changing_role_is_blocked_when_the_old_profile_owns_projects(): void
    {
        $user = User::factory()->contractor()->create();
        $contractor = Contractor::factory()->create(['user_id' => $user->id]);
        Project::factory()->for($contractor)->create();

        $this->expectException(ValidationException::class);
        $user->changeRole('customer');
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
