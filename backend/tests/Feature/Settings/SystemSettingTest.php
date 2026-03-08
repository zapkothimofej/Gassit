<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_can_list_system_settings(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/system-settings');

        $response->assertOk();
        // Seeded defaults from migration
        $keys = collect($response->json())->pluck('key')->toArray();
        $this->assertContains('invoice_day', $keys);
        $this->assertContains('login_max_attempts', $keys);
    }

    public function test_can_get_single_setting_by_key(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/system-settings/invoice_day');

        $response->assertOk();
        $response->assertJsonFragment(['key' => 'invoice_day', 'value' => '1']);
    }

    public function test_returns_404_for_unknown_key(): void
    {
        $this->actingAs($this->admin)->getJson('/api/system-settings/nonexistent_key')
            ->assertNotFound();
    }

    public function test_can_update_system_settings(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/system-settings', [
            'settings' => [
                ['key' => 'invoice_day', 'value' => '15'],
                ['key' => 'login_max_attempts', 'value' => '10'],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('system_settings', ['key' => 'invoice_day', 'value' => '15']);
        $this->assertDatabaseHas('system_settings', ['key' => 'login_max_attempts', 'value' => '10']);
    }

    public function test_update_is_logged_to_audit(): void
    {
        $this->actingAs($this->admin)->putJson('/api/system-settings', [
            'settings' => [
                ['key' => 'invoice_day', 'value' => '5'],
            ],
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'system_setting_updated']);
    }

    public function test_update_ignores_unknown_keys(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/system-settings', [
            'settings' => [
                ['key' => 'nonexistent_key', 'value' => 'something'],
            ],
        ]);

        $response->assertOk();
        $this->assertCount(0, $response->json());
    }

    public function test_update_validates_input(): void
    {
        $response = $this->actingAs($this->admin)->putJson('/api/system-settings', [
            'settings' => 'not_an_array',
        ]);

        $response->assertUnprocessable();
    }

    public function test_requires_admin_role(): void
    {
        $manager = User::factory()->create(['role' => 'main_manager']);
        $this->actingAs($manager)->getJson('/api/system-settings')->assertForbidden();
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/system-settings')->assertUnauthorized();
    }
}
