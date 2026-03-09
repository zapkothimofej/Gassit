<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_generates_secret_and_qr_uri(): void
    {
        $user = User::factory()->create(['active' => true]);
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/2fa/setup');

        $response->assertOk()
            ->assertJsonStructure(['secret', 'qr_code_uri']);

        $user->refresh();
        $this->assertNotNull($user->totp_secret);
    }

    public function test_enable_activates_2fa_with_valid_code(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'active' => true,
            'totp_secret' => $secret,
            'two_factor_enabled' => false,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/2fa/enable', ['code' => $code]);

        $response->assertOk();
        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
    }

    public function test_enable_rejects_invalid_code(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'active' => true,
            'totp_secret' => $secret,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/2fa/enable', ['code' => '000000']);

        $response->assertStatus(422);
    }

    public function test_login_returns_requires_2fa_when_enabled(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        User::factory()->create([
            'email' => 'user2fa@example.com',
            'password' => bcrypt('password123'),
            'active' => true,
            'totp_secret' => $secret,
            'two_factor_enabled' => true,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'user2fa@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson(['requires_2fa' => true])
            ->assertJsonStructure(['requires_2fa', 'temp_token']);
    }

    public function test_verify_issues_full_token_with_valid_code(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'active' => true,
            'totp_secret' => $secret,
            'two_factor_enabled' => true,
        ]);

        $tempToken = $user->createToken('temp-2fa')->plainTextToken;
        $code = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/auth/2fa/verify', [
            'temp_token' => $tempToken,
            'code' => $code,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'user']);
    }

    public function test_totp_secret_is_stored_encrypted_in_database(): void
    {
        $user = User::factory()->create(['active' => true]);
        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/2fa/setup');

        $response->assertOk();

        $returnedSecret = $response->json('secret');
        $dbRaw = \Illuminate\Support\Facades\DB::table('users')->where('id', $user->id)->value('totp_secret');

        // Plaintext secret must NOT be stored directly
        $this->assertNotEquals($returnedSecret, $dbRaw);
    }

    public function test_2fa_temp_token_is_rejected_after_5_minutes(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'active'             => true,
            'totp_secret'        => $secret,
            'two_factor_enabled' => true,
        ]);

        $tempToken = $user->createToken('temp-2fa')->plainTextToken;

        $this->travel(6)->minutes();

        $response = $this->postJson('/api/auth/2fa/verify', [
            'temp_token' => $tempToken,
            'code'       => '000000',
        ]);

        $response->assertStatus(401);
    }

    public function test_disable_requires_password_confirmation(): void
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'active' => true,
            'password' => bcrypt('mypassword'),
            'totp_secret' => $secret,
            'two_factor_enabled' => true,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/2fa/disable', ['password' => 'mypassword']);

        $response->assertOk();
        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->totp_secret);
    }
}
