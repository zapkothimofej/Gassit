<?php

namespace Tests\Feature\AuditLog;

use App\Models\AuditLog;
use App\Models\Park;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->other = User::factory()->create(['role' => 'rental_manager']);
    }

    private function createLog(array $attrs = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'user_id'    => $this->admin->id,
            'action'     => 'create',
            'model_type' => 'Park',
            'model_id'   => 1,
            'old_values' => null,
            'new_values' => ['name' => 'Test Park'],
            'ip_address' => '127.0.0.1',
        ], $attrs));
    }

    public function test_index_returns_paginated_logs(): void
    {
        $this->createLog();
        $this->createLog(['action' => 'update']);

        $response = $this->actingAs($this->admin)->getJson('/api/audit-logs');

        $response->assertOk();
        $response->assertJsonPath('total', 2);
        $response->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    }

    public function test_index_filter_by_user_id(): void
    {
        $this->createLog(['user_id' => $this->admin->id]);
        $this->createLog(['user_id' => $this->other->id]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/audit-logs?user_id={$this->admin->id}");

        $response->assertOk();
        $response->assertJsonPath('total', 1);
    }

    public function test_index_filter_by_model_type(): void
    {
        $this->createLog(['model_type' => 'Park']);
        $this->createLog(['model_type' => 'Unit']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/audit-logs?model_type=Park');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
    }

    public function test_index_filter_by_model_id(): void
    {
        $this->createLog(['model_id' => 1]);
        $this->createLog(['model_id' => 2]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/audit-logs?model_id=1');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
    }

    public function test_index_filter_by_date_range(): void
    {
        $jan = $this->createLog(['action' => 'create']);
        $jan->forceFill(['created_at' => '2026-01-15 10:00:00'])->save();

        $mar = $this->createLog(['action' => 'update']);
        $mar->forceFill(['created_at' => '2026-03-10 10:00:00'])->save();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/audit-logs?from=2026-03-01&to=2026-03-31');

        $response->assertOk();
        $response->assertJsonPath('total', 1);
        $response->assertJsonFragment(['action' => 'update']);
    }

    public function test_show_returns_audit_log_detail(): void
    {
        $log = $this->createLog();

        $response = $this->actingAs($this->admin)
            ->getJson("/api/audit-logs/{$log->id}");

        $response->assertOk();
        $response->assertJsonFragment([
            'id'         => $log->id,
            'action'     => 'create',
            'model_type' => 'Park',
        ]);
        $response->assertJsonStructure(['id', 'action', 'model_type', 'model_id', 'new_values', 'user']);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/audit-logs/999');

        $response->assertNotFound();
    }

    public function test_export_returns_csv(): void
    {
        $this->createLog();
        $this->createLog(['action' => 'delete']);

        $response = $this->actingAs($this->admin)->getJson('/api/audit-logs/export');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('id,user_id,user_name', $response->getContent());
        $this->assertStringContainsString('create', $response->getContent());
        $this->assertStringContainsString('delete', $response->getContent());
    }

    public function test_export_filters_by_date(): void
    {
        $jan = $this->createLog(['action' => 'create', 'model_type' => 'Park']);
        $jan->forceFill(['created_at' => '2026-01-10 10:00:00'])->save();

        $mar = $this->createLog(['action' => 'update', 'model_type' => 'Unit']);
        $mar->forceFill(['created_at' => '2026-03-05 10:00:00'])->save();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/audit-logs/export?from=2026-03-01&to=2026-03-31');

        $response->assertOk();
        $content = $response->getContent();
        $this->assertStringContainsString('update', $content);
        $this->assertStringNotContainsString('2026-01-10', $content);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $response = $this->actingAs($this->other)->getJson('/api/audit-logs');

        $response->assertForbidden();
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/audit-logs')->assertUnauthorized();
    }
}
