<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_is_sent_to_an_existing_user(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_an_unknown_email_is_acknowledged_without_leaking_or_sending(): void
    {
        Notification::fake();

        $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account matches that email, a reset link has been sent.');

        Notification::assertNothingSent();
    }

    public function test_a_valid_token_resets_the_password_and_revokes_existing_tokens(): void
    {
        $user = User::factory()->create();
        $user->createToken('api'); // a pre-existing session
        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'fresh-password',
            'password_confirmation' => 'fresh-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('fresh-password', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_an_invalid_token_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/reset-password', [
            'token' => 'totally-wrong-token',
            'email' => $user->email,
            'password' => 'fresh-password',
            'password_confirmation' => 'fresh-password',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }
}
