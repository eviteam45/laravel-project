<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectApiTest extends TestCase
{
    use RefreshDatabase;

    private function contractorUser(): array
    {
        $user = User::factory()->contractor()->create();
        $contractor = Contractor::factory()->create(['user_id' => $user->id]);

        return [$user, $contractor];
    }

    public function test_index_is_scoped_to_the_contractors_own_projects(): void
    {
        [$user, $contractor] = $this->contractorUser();
        Project::factory(2)->for($contractor)->create();
        Project::factory(3)->create(); // other contractors' projects

        Sanctum::actingAs($user);

        $this->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_index_filters_by_status_and_search(): void
    {
        [$user, $contractor] = $this->contractorUser();
        Project::factory()->for($contractor)->create(['name' => 'Solar Roof Alpha', 'status' => 'installed']);
        Project::factory()->for($contractor)->create(['name' => 'Battery Beta', 'status' => 'draft']);

        Sanctum::actingAs($user);

        $this->getJson('/api/projects?status=installed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Solar Roof Alpha');

        $this->getJson('/api/projects?search=Beta')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Battery Beta');
    }

    public function test_a_contractor_can_create_a_project_for_themselves(): void
    {
        [$user, $contractor] = $this->contractorUser();
        $customer = Customer::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/projects', [
            'name' => 'New Install',
            'customer_id' => $customer->id,
            'capacity_kw' => 12.5,
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'New Install');

        // contractor_id is forced to the authenticated contractor, ignoring any input.
        $this->assertDatabaseHas('projects', [
            'name' => 'New Install',
            'contractor_id' => $contractor->id,
            'customer_id' => $customer->id,
        ]);
    }

    public function test_a_customer_cannot_create_a_project(): void
    {
        $user = User::factory()->customer()->create();
        Customer::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/projects', [
            'name' => 'Nope',
            'customer_id' => Customer::factory()->create()->id,
        ])->assertForbidden();
    }

    public function test_a_contractor_cannot_view_or_update_another_contractors_project(): void
    {
        [$user] = $this->contractorUser();
        $other = Project::factory()->create(); // belongs to a different contractor

        Sanctum::actingAs($user);

        $this->getJson("/api/projects/{$other->id}")->assertForbidden();
        $this->putJson("/api/projects/{$other->id}", ['name' => 'Hijack'])->assertForbidden();
        $this->deleteJson("/api/projects/{$other->id}")->assertForbidden();
    }

    public function test_a_contractor_can_update_and_delete_their_own_project(): void
    {
        [$user, $contractor] = $this->contractorUser();
        $project = Project::factory()->for($contractor)->create(['status' => 'draft']);

        Sanctum::actingAs($user);

        // Status is no longer settable via update — only name/address/etc.
        $this->putJson("/api/projects/{$project->id}", ['name' => 'Renamed Project'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed Project')
            ->assertJsonPath('data.status', 'draft');

        $this->deleteJson("/api/projects/{$project->id}")->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_an_admin_creates_a_project_by_selecting_a_contractor(): void
    {
        $contractor = Contractor::factory()->create();
        $customer = Customer::factory()->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/projects', [
            'name' => 'Admin Project',
            'contractor_id' => $contractor->id,
            'customer_id' => $customer->id,
        ])->assertCreated();

        $this->assertDatabaseHas('projects', [
            'name' => 'Admin Project',
            'contractor_id' => $contractor->id,
        ]);
    }

    public function test_an_admin_without_a_contractor_gets_a_clear_error_not_a_500(): void
    {
        $customer = Customer::factory()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/projects', [
            'name' => 'No Contractor',
            'customer_id' => $customer->id,
        ])->assertStatus(422)->assertJsonValidationErrors('contractor_id');
    }

    public function test_only_admins_can_list_contractor_options(): void
    {
        Contractor::factory(2)->create();

        [$contractorUser] = $this->contractorUser();
        Sanctum::actingAs($contractorUser);
        $this->getJson('/api/contractors/options')->assertForbidden();

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/contractors/options')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'company_name']]]);
    }

    public function test_contractors_can_list_customer_options_but_customers_cannot(): void
    {
        Customer::factory(2)->create();

        [$contractor] = $this->contractorUser();
        Sanctum::actingAs($contractor);
        $this->getJson('/api/customers/options')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'full_name']]]);

        $customerUser = User::factory()->customer()->create();
        Customer::factory()->create(['user_id' => $customerUser->id]);
        Sanctum::actingAs($customerUser);
        $this->getJson('/api/customers/options')->assertForbidden();
    }

    public function test_creating_an_application_is_limited_to_one_per_project(): void
    {
        [$user, $contractor] = $this->contractorUser();
        $project = Project::factory()->for($contractor)->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/applications', ['project_id' => $project->id])
            ->assertCreated()
            ->assertJsonPath('data.status', 'started')
            ->assertJsonPath('data.current_step', 'eligibility');

        // Second attempt is rejected.
        $this->postJson('/api/applications', ['project_id' => $project->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('project_id');
    }
}
