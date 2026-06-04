<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_register_and_gets_a_customer_profile(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'customer',
            'address' => '1 Solar Way',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'token'])
            ->assertJsonPath('user.role', 'customer');
        $response->assertJsonMissingPath('user.password');

        $userId = $response->json('user.id');
        $this->assertDatabaseHas('customers', ['user_id' => $userId, 'full_name' => 'Jane Doe']);
        $this->assertDatabaseMissing('contractors', ['user_id' => $userId]);
    }

    public function test_a_customer_can_register_with_an_explicit_null_company_name(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Nadia Null',
            'email' => 'nadia@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'customer',
            'company_name' => null,
            'license_no' => null,
            'region' => null,
        ])->assertCreated()->assertJsonPath('user.role', 'customer');
    }

    public function test_a_contractor_registration_requires_and_creates_a_company_profile(): void
    {

        $this->postJson('/api/register', [
            'name' => 'Bob Builder',
            'email' => 'bob@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'contractor',
        ])->assertStatus(422)->assertJsonValidationErrors('company_name');

        $response = $this->postJson('/api/register', [
            'name' => 'Bob Builder',
            'email' => 'bob@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'contractor',
            'company_name' => 'Bob Solar LLC',
        ]);

        $response->assertCreated()->assertJsonPath('user.role', 'contractor');
        $this->assertDatabaseHas('contractors', [
            'user_id' => $response->json('user.id'),
            'company_name' => 'Bob Solar LLC',
        ]);
    }

    public function test_self_registration_cannot_create_an_admin(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ])->assertStatus(422)->assertJsonValidationErrors('role');
    }

    public function test_registration_validates_input(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'customer',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_a_user_can_log_in_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'john@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token'])
            ->assertJsonPath('user.email', 'john@example.com');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'john@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_the_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/user')->assertUnauthorized();
    }

    public function test_an_authenticated_user_can_fetch_their_profile(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('id', $user->id)
            ->assertJsonPath('email', $user->email);
    }

    public function test_login_is_rate_limited(): void
    {

        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
                ->assertStatus(422);
        }

        $this->postJson('/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertStatus(429);
    }

    public function test_a_user_can_log_out_and_the_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
