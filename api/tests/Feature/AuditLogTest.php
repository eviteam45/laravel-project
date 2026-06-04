<?php

namespace Tests\Feature;

use App\Models\IncentiveApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_view_and_filter_audit_logs(): void
    {
        $admin = User::factory()->admin()->create();
        $application = IncentiveApplication::factory()->create(['status' => 'submitted']);

        Sanctum::actingAs($admin);

        $this->postJson("/api/applications/{$application->id}/transition", ['to' => 'under_review'])->assertOk();

        $this->getJson('/api/audit-logs')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'action', 'subject_type', 'subject_id', 'user']], 'meta'])
            ->assertJsonPath('data.0.action', 'status_changed')
            ->assertJsonPath('data.0.subject_type', 'IncentiveApplication');

        $this->getJson('/api/audit-logs?action=status_changed')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/audit-logs?action=nonexistent')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_non_admins_cannot_view_audit_logs(): void
    {
        Sanctum::actingAs(User::factory()->contractor()->create());
        $this->getJson('/api/audit-logs')->assertForbidden();
    }
}
