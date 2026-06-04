<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $contractorUser;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->contractorUser = User::factory()->contractor()->create();
        $contractor = Contractor::factory()->create(['user_id' => $this->contractorUser->id]);
        $this->project = Project::factory()->for($contractor)->create(['status' => 'draft']);
    }

    private function transition(array $body)
    {
        return $this->postJson("/api/projects/{$this->project->id}/transition", $body);
    }

    public function test_a_contractor_can_submit_their_draft_project(): void
    {
        Sanctum::actingAs($this->contractorUser);

        $this->transition(['to' => 'submitted'])
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'status_changed',
            'subject_type' => Project::class,
            'subject_id' => $this->project->id,
        ]);
    }

    public function test_illegal_project_transitions_are_rejected_with_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->transition(['to' => 'approved'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    public function test_only_admins_can_approve(): void
    {
        $this->project->update(['status' => 'in_review']);

        Sanctum::actingAs($this->contractorUser);
        $this->transition(['to' => 'approved'])->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->transition(['to' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_a_contractor_cannot_transition_a_project_they_do_not_own(): void
    {
        $other = Project::factory()->create(['status' => 'draft']);
        Sanctum::actingAs($this->contractorUser);

        $this->postJson("/api/projects/{$other->id}/transition", ['to' => 'submitted'])
            ->assertForbidden();
    }

    public function test_a_project_transition_notifies_related_parties_except_the_actor(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($this->contractorUser);

        $this->transition(['to' => 'submitted'])->assertOk();

        foreach ([$admin, $this->project->customer->user] as $user) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $user->id,
                'type' => 'project_submitted',
            ]);
        }

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->contractorUser->id,
            'type' => 'project_submitted',
        ]);
    }
}
