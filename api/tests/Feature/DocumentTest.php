<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    private function contractorOwner(): array
    {
        $user = User::factory()->contractor()->create();
        $contractor = Contractor::factory()->create(['user_id' => $user->id]);

        return [$user, $contractor];
    }

    public function test_a_contractor_can_upload_a_document_to_their_project(): void
    {
        Storage::fake('local');
        [$user, $contractor] = $this->contractorOwner();
        $project = Project::factory()->for($contractor)->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$project->id}/documents", [
            'file' => UploadedFile::fake()->create('contract.pdf', 50, 'application/pdf'),
            'type' => 'contract',
        ])->assertCreated()->assertJsonStructure(['data' => ['id', 'file_name', 'download_url']]);

        $this->assertDatabaseHas('documents', [
            'documentable_type' => Project::class,
            'documentable_id' => $project->id,
            'type' => 'contract',
        ]);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_a_non_owner_cannot_upload_to_a_project(): void
    {
        Storage::fake('local');
        [$user] = $this->contractorOwner();
        $other = Project::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/projects/{$other->id}/documents", [
            'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            'type' => 'contract',
        ])->assertForbidden();
    }

    public function test_the_owner_can_delete_a_project_document(): void
    {
        Storage::fake('local');
        [$user, $contractor] = $this->contractorOwner();
        $project = Project::factory()->for($contractor)->create();

        Sanctum::actingAs($user);

        $id = $this->postJson("/api/projects/{$project->id}/documents", [
            'file' => UploadedFile::fake()->create('c.pdf', 10, 'application/pdf'),
            'type' => 'contract',
        ])->json('data.id');

        $this->deleteJson("/api/documents/{$id}")->assertOk();
        $this->assertDatabaseMissing('documents', ['id' => $id]);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }
}
