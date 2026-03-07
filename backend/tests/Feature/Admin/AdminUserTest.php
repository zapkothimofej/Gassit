<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Park;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->token = $this->admin->createToken('api-token')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    // ---- User tests ----

    public function test_list_users_requires_admin(): void
    {
        $nonAdmin = User::factory()->create(['role' => 'rental_manager', 'active' => true]);
        $token = $nonAdmin->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    public function test_list_users_returns_paginated_results(): void
    {
        User::factory()->count(3)->create();

        $this->withHeaders($this->auth())
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    }

    public function test_list_users_filters_by_role(): void
    {
        User::factory()->create(['role' => 'accountant']);
        User::factory()->create(['role' => 'park_worker']);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/admin/users?role=accountant')
            ->assertOk();

        foreach ($response->json('data') as $user) {
            $this->assertEquals('accountant', $user['role']);
        }
    }

    public function test_create_user(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/admin/users', [
                'name'  => 'New User',
                'email' => 'newuser@example.com',
                'role'  => 'rental_manager',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['email' => 'newuser@example.com', 'role' => 'rental_manager']);

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'model_type' => User::class]);
    }

    public function test_update_user(): void
    {
        $user = User::factory()->create(['role' => 'park_worker', 'active' => true]);

        $this->withHeaders($this->auth())
            ->putJson("/api/admin/users/{$user->id}", ['role' => 'accountant'])
            ->assertOk()
            ->assertJsonFragment(['role' => 'accountant']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'update', 'model_id' => $user->id]);
    }

    public function test_delete_user_deactivates_not_destroys(): void
    {
        $user = User::factory()->create(['active' => true]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/admin/users/{$user->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'User deactivated.']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'deactivate', 'model_id' => $user->id]);
    }

    public function test_sync_parks(): void
    {
        $user = User::factory()->create();
        $parks = Park::factory()->count(2)->create();

        $this->withHeaders($this->auth())
            ->postJson("/api/admin/users/{$user->id}/parks", [
                'park_ids' => $parks->pluck('id')->toArray(),
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'park_ids']);

        $this->assertCount(2, $user->fresh()->parks);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sync_parks', 'model_id' => $user->id]);
    }

    // ---- Employee tests ----

    public function test_create_employee(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/admin/employees', [
                'first_name' => 'Anna',
                'last_name'  => 'Müller',
                'email'      => 'anna@example.com',
                'role_title' => 'Manager',
                'hire_date'  => '2024-01-01',
            ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['first_name' => 'Anna', 'last_name' => 'Müller']);

        $this->assertDatabaseHas('employees', ['email' => 'anna@example.com']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'model_type' => Employee::class]);
    }

    public function test_list_employees(): void
    {
        Employee::factory()->count(2)->create();

        $this->withHeaders($this->auth())
            ->getJson('/api/admin/employees')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_update_employee(): void
    {
        $employee = Employee::factory()->create(['role_title' => 'Worker']);

        $this->withHeaders($this->auth())
            ->putJson("/api/admin/employees/{$employee->id}", ['role_title' => 'Senior Worker'])
            ->assertOk()
            ->assertJsonFragment(['role_title' => 'Senior Worker']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'update', 'model_type' => Employee::class]);
    }

    public function test_delete_employee(): void
    {
        $employee = Employee::factory()->create();

        $this->withHeaders($this->auth())
            ->deleteJson("/api/admin/employees/{$employee->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Employee deleted.']);

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_unauthenticated_request_blocked(): void
    {
        $this->getJson('/api/admin/users')->assertStatus(401);
    }
}
