<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_cannot_access_user_administration(): void
    {
        $user = User::factory()->contractor()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/users')->assertForbidden();
        $this->patchJson("/api/users/{$user->id}/role", ['role' => 'admin'])->assertForbidden();
    }

    public function test_an_admin_can_list_and_filter_users(): void
    {
        User::factory(3)->customer()->create();
        User::factory(2)->contractor()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson('/api/users')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role']], 'meta']);

        $this->getJson('/api/users?role=contractor')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_admin_can_change_a_role_and_the_profile_is_backfilled(): void
    {
        $target = User::factory()->customer()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/users/{$target->id}/role", ['role' => 'contractor'])
            ->assertOk()
            ->assertJsonPath('data.role', 'contractor');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'contractor']);

        $this->assertDatabaseHas('contractors', ['user_id' => $target->id]);
    }

    public function test_an_admin_cannot_change_their_own_role(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->patchJson("/api/users/{$admin->id}/role", ['role' => 'customer'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }

    public function test_an_invalid_role_is_rejected(): void
    {
        $target = User::factory()->customer()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->patchJson("/api/users/{$target->id}/role", ['role' => 'superuser'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');
    }
}
