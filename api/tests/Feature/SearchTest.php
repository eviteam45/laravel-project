<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\IncentiveApplication;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * MySQL FULLTEXT search only sees committed rows, so these tests use
 * DatabaseTruncation (commit + truncate) rather than the suite's default
 * transactional RefreshDatabase.
 */
class SearchTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        $this->truncateTablesForAllConnections();

        parent::tearDown();
    }

    public function test_projects_are_searched_by_name(): void
    {
        $user = User::factory()->contractor()->create();
        $contractor = Contractor::factory()->create(['user_id' => $user->id]);

        Project::factory()->for($contractor)->create(['name' => 'Solar Roof Alpha', 'status' => 'installed']);
        Project::factory()->for($contractor)->create(['name' => 'Battery Beta', 'status' => 'draft']);

        Sanctum::actingAs($user);

        $this->getJson('/api/projects?search=Beta')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Battery Beta');
    }

    public function test_applications_are_searched_by_project_name_and_contractor(): void
    {
        $north = Contractor::factory()->create(['region' => 'North', 'company_name' => 'NorthSolar']);
        $alpha = Project::factory()->for($north)->create(['name' => 'Alpha Roof']);
        IncentiveApplication::factory()->for($alpha)->create();

        $south = Contractor::factory()->create(['region' => 'South', 'company_name' => 'SouthSolar']);
        $beta = Project::factory()->for($south)->create(['name' => 'Beta Roof']);
        IncentiveApplication::factory()->for($beta)->create();

        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/applications?search=Alpha')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/applications?search=NorthSolar')->assertOk()->assertJsonCount(1, 'data');
    }
}
