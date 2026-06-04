<?php

namespace Tests\Feature;

use App\Jobs\ProcessApplicationTransition;
use App\Models\Contractor;
use App\Models\Customer;
use App\Models\IncentiveApplication;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Notifications\IncentiveReservedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

class ProcessApplicationTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function reservedApplication(): IncentiveApplication
    {
        return IncentiveApplication::factory()->create([
            'status' => 'reserved',
            'incentive_amount' => 1000,
        ]);
    }

    public function test_the_reserved_job_is_idempotent(): void
    {
        $app = $this->reservedApplication();
        $customerUserId = $app->project->customer->user_id;

        (new ProcessApplicationTransition($app->id, 'under_review', 'reserved'))->handle();
        (new ProcessApplicationTransition($app->id, 'under_review', 'reserved'))->handle();

        $this->assertSame(1, $app->payments()->where('status', 'scheduled')->count());
        $this->assertSame(1, Notification::where('user_id', $customerUserId)
            ->where('type', 'application_reserved')->count());
    }

    public function test_reserving_sends_a_queued_email_to_the_customer(): void
    {
        NotificationFacade::fake();

        $app = $this->reservedApplication();
        $customerUser = $app->project->customer->user;

        (new ProcessApplicationTransition($app->id, 'under_review', 'reserved'))->handle();

        NotificationFacade::assertSentTo($customerUser, IncentiveReservedNotification::class);
    }

    public function test_it_notifies_all_related_parties_except_the_actor(): void
    {
        $contractorUser = User::factory()->contractor()->create();
        $contractor = Contractor::factory()->create(['user_id' => $contractorUser->id]);
        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->create(['user_id' => $customerUser->id]);
        $project = Project::factory()->for($contractor)->for($customer)->create();
        $application = IncentiveApplication::factory()->for($project)->create(['status' => 'submitted']);

        $actor = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        (new ProcessApplicationTransition($application->id, 'submitted', 'under_review', $actor->id))->handle();

        foreach ([$contractorUser, $customerUser, $otherAdmin] as $u) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $u->id,
                'type' => 'application_under_review',
            ]);
        }

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $actor->id,
            'type' => 'application_under_review',
        ]);
    }

    public function test_marking_paid_settles_the_scheduled_payment(): void
    {
        $app = $this->reservedApplication();
        (new ProcessApplicationTransition($app->id, 'under_review', 'reserved'))->handle();

        (new ProcessApplicationTransition($app->id, 'reserved', 'paid'))->handle();

        $this->assertSame(1, $app->payments()->where('status', 'paid')->count());
        $this->assertSame(0, $app->payments()->where('status', 'scheduled')->count());
    }
}
