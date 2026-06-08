<?php

namespace Tests\Feature;

use App\Jobs\ProcessProjectTransition;
use App\Models\Contractor;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Services\TransitionNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessProjectTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_all_related_parties_except_the_actor(): void
    {
        $contractorUser = User::factory()->contractor()->create();
        $contractor = Contractor::factory()->create(['user_id' => $contractorUser->id]);
        $customerUser = User::factory()->customer()->create();
        $customer = Customer::factory()->create(['user_id' => $customerUser->id]);
        $project = Project::factory()->for($contractor)->for($customer)->create(['status' => 'submitted']);

        $otherAdmin = User::factory()->admin()->create();

        (new ProcessProjectTransition($project->id, 'draft', 'submitted', $contractorUser->id))
            ->handle(app(TransitionNotifier::class));

        foreach ([$customerUser, $otherAdmin] as $u) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $u->id,
                'type' => 'project_submitted',
            ]);
        }

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $contractorUser->id,
            'type' => 'project_submitted',
        ]);
    }

    public function test_it_is_idempotent_per_project(): void
    {
        $project = Project::factory()->create(['status' => 'submitted']);
        $customerUserId = $project->customer->user_id;

        $notifier = app(TransitionNotifier::class);
        (new ProcessProjectTransition($project->id, 'draft', 'submitted'))->handle($notifier);
        (new ProcessProjectTransition($project->id, 'draft', 'submitted'))->handle($notifier);

        $this->assertSame(1, Notification::where('user_id', $customerUserId)
            ->where('type', 'project_submitted')->count());
    }

    public function test_a_missing_project_is_a_no_op(): void
    {
        (new ProcessProjectTransition(999999, 'draft', 'submitted'))
            ->handle(app(TransitionNotifier::class));

        $this->assertDatabaseCount('notifications', 0);
    }
}
