<?php

namespace Tests\Feature;

use App\Jobs\ProcessApplicationTransition;
use App\Models\IncentiveApplication;
use App\Models\Notification;
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

        // Simulate the job running twice (e.g. a queue retry).
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

    public function test_marking_paid_settles_the_scheduled_payment(): void
    {
        $app = $this->reservedApplication();
        (new ProcessApplicationTransition($app->id, 'under_review', 'reserved'))->handle();

        (new ProcessApplicationTransition($app->id, 'reserved', 'paid'))->handle();

        $this->assertSame(1, $app->payments()->where('status', 'paid')->count());
        $this->assertSame(0, $app->payments()->where('status', 'scheduled')->count());
    }
}
