<?php

namespace Tests\Feature;

use App\Jobs\ProcessApplicationTransition;
use App\Models\Contractor;
use App\Models\Customer;
use App\Models\IncentiveApplication;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $contractorUser;

    private User $customerUser;

    private IncentiveApplication $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $this->contractorUser = User::factory()->contractor()->create();
        $contractor = Contractor::factory()->create(['user_id' => $this->contractorUser->id]);

        $this->customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->create(['user_id' => $this->customerUser->id]);

        $project = Project::factory()->for($contractor)->for($customer)->create();
        $this->application = IncentiveApplication::factory()->for($project)->create([
            'status' => 'submitted',
        ]);
    }

    private function transition(array $body)
    {
        return $this->postJson("/api/applications/{$this->application->id}/transition", $body);
    }

    public function test_an_admin_can_advance_status_and_an_audit_entry_is_written(): void
    {
        Sanctum::actingAs($this->admin);

        $this->transition(['to' => 'under_review'])
            ->assertOk()
            ->assertJsonPath('data.status', 'under_review');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'status_changed',
            'subject_type' => IncentiveApplication::class,
            'subject_id' => $this->application->id,
        ]);
    }

    public function test_illegal_transitions_are_rejected_with_422(): void
    {
        Sanctum::actingAs($this->admin);

        $this->transition(['to' => 'paid'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    public function test_a_contractor_cannot_perform_an_admin_only_transition(): void
    {

        Sanctum::actingAs($this->contractorUser);

        $this->transition(['to' => 'under_review'])->assertForbidden();
    }

    public function test_a_customer_can_withdraw_their_application(): void
    {
        Sanctum::actingAs($this->customerUser);

        $this->transition(['to' => 'withdrawn'])
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');
    }

    public function test_reserving_requires_an_incentive_amount(): void
    {
        $this->application->update(['status' => 'under_review']);
        Sanctum::actingAs($this->admin);

        $this->transition(['to' => 'reserved'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('incentive_amount');
    }

    public function test_a_transition_queues_the_side_effect_job_after_commit(): void
    {
        Bus::fake();
        Sanctum::actingAs($this->admin);

        $this->transition(['to' => 'under_review'])->assertOk();

        // Notifications/payments are produced by the queued job (covered by
        // ProcessApplicationTransitionTest); here we assert it is enqueued.
        Bus::assertDispatched(
            ProcessApplicationTransition::class,
            fn (ProcessApplicationTransition $job) => $job->applicationId === $this->application->id
                && $job->to === 'under_review'
                && $job->actorId === $this->admin->id,
        );
    }

    public function test_reserving_persists_the_amount_and_queues_the_job(): void
    {
        Bus::fake();
        $this->application->update(['status' => 'under_review']);
        Sanctum::actingAs($this->admin);

        $this->transition(['to' => 'reserved', 'incentive_amount' => 4200])
            ->assertOk()
            ->assertJsonPath('data.status', 'reserved')
            ->assertJsonPath('data.incentive_amount', '4200.00');

        Bus::assertDispatched(
            ProcessApplicationTransition::class,
            fn (ProcessApplicationTransition $job) => $job->applicationId === $this->application->id
                && $job->to === 'reserved',
        );
    }
}
