<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\IncentiveApplication;
use App\Models\IncentivePayment;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_are_scoped_to_the_contractor(): void
    {
        $user = User::factory()->contractor()->create();
        $contractor = Contractor::factory()->create(['user_id' => $user->id]);

        Project::factory()->for($contractor)->create(['status' => 'draft']);
        Project::factory(2)->for($contractor)->create(['status' => 'installed']);

        // Another contractor's projects — must be excluded.
        Project::factory(4)->create();

        $reserved = IncentiveApplication::factory()
            ->for(Project::factory()->for($contractor)->state(['status' => 'submitted']))
            ->create(['status' => 'reserved', 'incentive_amount' => 1000]);
        IncentivePayment::factory()->for($reserved, 'application')->create(['status' => 'paid', 'amount' => 800]);
        IncentivePayment::factory()->for($reserved, 'application')->create(['status' => 'scheduled', 'amount' => 200]);

        Notification::factory(2)->unread()->for($user)->create();
        Notification::factory()->for($user)->create(['read_at' => now()]);

        Sanctum::actingAs($user);

        $this->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('projects.total', 4) // 3 + 1 (the reserved app's project)
            ->assertJsonPath('projects.by_status.installed', 2)
            ->assertJsonPath('projects.by_status.draft', 1)
            ->assertJsonPath('applications.total', 1)
            ->assertJsonPath('applications.by_status.reserved', 1)
            ->assertJsonPath('incentives.reserved_total', 1000)
            ->assertJsonPath('incentives.paid_total', 800)
            ->assertJsonPath('incentives.scheduled_total', 200)
            ->assertJsonPath('notifications.unread_count', 2)
            ->assertJsonCount(1, 'recent_applications');
    }

    public function test_notifications_list_is_scoped_and_can_be_marked_read(): void
    {
        $user = User::factory()->create();
        Notification::factory(2)->unread()->for($user)->create();
        Notification::factory()->for(User::factory())->create(); // someone else's

        Sanctum::actingAs($user);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'type', 'payload', 'is_read', 'created_at']]]);

        $this->postJson('/api/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('marked_read', 2);

        $this->assertSame(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_a_user_cannot_mark_another_users_notification_read(): void
    {
        $user = User::factory()->create();
        $foreign = Notification::factory()->unread()->for(User::factory())->create();

        Sanctum::actingAs($user);

        $this->postJson("/api/notifications/{$foreign->id}/read")->assertForbidden();
    }
}
