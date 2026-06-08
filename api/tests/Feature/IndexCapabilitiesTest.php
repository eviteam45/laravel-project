<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\IncentiveApplication;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IndexCapabilitiesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_projects_index_returns_pagination_meta(): void
    {
        Project::factory(5)->create();
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/projects?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'last_page', 'per_page', 'total']]);
    }

    public function test_projects_can_be_filtered_by_region(): void
    {
        $north = Contractor::factory()->create(['region' => 'North']);
        $south = Contractor::factory()->create(['region' => 'South']);
        Project::factory(2)->for($north)->create();
        Project::factory(3)->for($south)->create();

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/projects?region=North')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_applications_support_region_filter(): void
    {
        $north = Contractor::factory()->create(['region' => 'North', 'company_name' => 'NorthSolar']);
        $alpha = Project::factory()->for($north)->create(['name' => 'Alpha Roof']);
        IncentiveApplication::factory()->for($alpha)->create();

        $south = Contractor::factory()->create(['region' => 'South', 'company_name' => 'SouthSolar']);
        $beta = Project::factory()->for($south)->create(['name' => 'Beta Roof']);
        IncentiveApplication::factory()->for($beta)->create();

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/applications?region=North')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_projects_sort_is_whitelisted_and_stable(): void
    {
        Project::factory()->create(['name' => 'B project']);
        Project::factory()->create(['name' => 'A project']);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/projects?sort=name&dir=asc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'A project');

        $this->getJson('/api/projects?sort=capacity_kw);DROP&dir=asc')->assertOk();
    }

    public function test_projects_index_has_no_n_plus_one(): void
    {
        Sanctum::actingAs($this->admin());

        Project::factory(2)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/projects?per_page=100')->assertOk();
        $withFew = count(DB::getQueryLog());

        Project::factory(13)->create();
        DB::flushQueryLog();
        $this->getJson('/api/projects?per_page=100')->assertOk();
        $withMany = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withFew, $withMany, "N+1 on projects index: {$withFew} vs {$withMany} queries");
    }

    public function test_applications_index_has_no_n_plus_one(): void
    {
        Sanctum::actingAs($this->admin());

        IncentiveApplication::factory(2)->create();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson('/api/applications?per_page=100')->assertOk();
        $withFew = count(DB::getQueryLog());

        IncentiveApplication::factory(13)->create();
        DB::flushQueryLog();
        $this->getJson('/api/applications?per_page=100')->assertOk();
        $withMany = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($withFew, $withMany, "N+1 on applications index: {$withFew} vs {$withMany} queries");
    }

    public function test_same_endpoint_returns_different_rows_per_role(): void
    {
        $contractor = Contractor::factory()->create();
        $mine = Project::factory()->for($contractor)->create();
        Project::factory(3)->create();

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(4, 'data');

        Sanctum::actingAs($contractor->user);
        $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }
}
