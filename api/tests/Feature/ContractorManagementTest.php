<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_access_contractor_management(): void
    {
        $contractorUser = User::factory()->contractor()->create();
        Sanctum::actingAs($contractorUser);

        $this->getJson('/api/contractors')->assertForbidden();
        $this->postJson('/api/contractors', [])->assertForbidden();
    }

    public function test_an_admin_can_list_contractors(): void
    {
        Contractor::factory(3)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/contractors')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'company_name', 'status', 'projects_count', 'user']]]);
    }

    public function test_an_admin_can_provision_a_contractor_with_a_user_account(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $response = $this->postJson('/api/contractors', [
            'name' => 'Bright Solar',
            'email' => 'ops@brightsolar.test',
            'password' => 'password123',
            'company_name' => 'Bright Solar LLC',
            'region' => 'North',
        ]);

        $response->assertCreated()->assertJsonPath('data.company_name', 'Bright Solar LLC');

        $this->assertDatabaseHas('users', ['email' => 'ops@brightsolar.test', 'role' => 'contractor']);
        $this->assertDatabaseHas('contractors', ['company_name' => 'Bright Solar LLC', 'region' => 'North']);
    }

    public function test_an_admin_can_update_a_contractor_status(): void
    {
        $contractor = Contractor::factory()->create(['status' => 'active']);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->putJson("/api/contractors/{$contractor->id}", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
    }

    public function test_a_contractor_with_projects_cannot_be_deleted(): void
    {
        $contractor = Contractor::factory()->create();
        Project::factory()->for($contractor)->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson("/api/contractors/{$contractor->id}")
            ->assertStatus(422)
            ->assertJsonValidationErrors('contractor');
    }

    public function test_an_admin_can_delete_a_contractor_without_projects(): void
    {
        $contractor = Contractor::factory()->create();
        $userId = $contractor->user_id;
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->deleteJson("/api/contractors/{$contractor->id}")->assertOk();

        $this->assertDatabaseMissing('contractors', ['id' => $contractor->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
    }
}
