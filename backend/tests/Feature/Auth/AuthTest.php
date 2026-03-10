<?php

namespace Tests\Feature\Auth;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::updateOrCreate(['key' => 'login_max_attempts'], ['value' => '3']);
    }

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user' => ['id', 'name', 'email', 'role', 'parks']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'active' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message', 'errors']);
    }

    public function test_login_locks_account_after_max_attempts(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
            'active' => true,
            'login_attempts' => 0,
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong',
            ]);
        }

        $user->refresh();
        $this->assertFalse($user->active);
    }

    public function test_login_fails_when_account_inactive(): void
    {
        User::factory()->create([
            'email' => 'locked@example.com',
            'password' => bcrypt('password123'),
            'active' => false,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'locked@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create(['active' => true]);
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertOk()->assertJson(['message' => 'Logged out successfully.']);
    }

    public function test_refresh_issues_new_token(): void
    {
        $user = User::factory()->create(['active' => true]);
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure(['token', 'token_type']);

        $this->assertNotEquals($token, $response->json('token'));
    }

    public function test_forgot_password_returns_ok_for_valid_email(): void
    {
        User::factory()->create(['email' => 'test@example.com', 'active' => true]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertOk()->assertJsonStructure(['message']);
    }

    public function test_unauthenticated_request_to_protected_route(): void
    {
        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);
    }
}
