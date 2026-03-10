<?php

namespace Tests\Feature\Task;

use App\Models\Notification;
use App\Models\Park;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $worker;
    private Park $park;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create(['role' => 'main_manager']);
        $this->worker  = User::factory()->create(['role' => 'park_worker']);
        $this->park    = Park::factory()->create();

        $this->worker->parks()->attach($this->park->id);
    }

    public function test_can_list_tasks(): void
    {
        Task::factory()->count(3)->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/tasks');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_tasks_by_park(): void
    {
        Task::factory()->create(['park_id' => $this->park->id, 'created_by' => $this->manager->id]);

        $otherPark = Park::factory()->create();
        Task::factory()->create(['park_id' => $otherPark->id, 'created_by' => $this->manager->id]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/tasks?park_id={$this->park->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_park_worker_sees_only_own_tasks(): void
    {
        Task::factory()->create([
            'park_id'     => $this->park->id,
            'created_by'  => $this->manager->id,
            'assigned_to' => $this->worker->id,
        ]);
        Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->worker)
            ->getJson('/api/tasks');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_create_task(): void
    {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/tasks', [
                'park_id'  => $this->park->id,
                'type'     => 'general',
                'title'    => 'Fix entrance door',
                'priority' => 'high',
                'due_date' => now()->addDays(5)->format('Y-m-d'),
            ]);

        $response->assertCreated();
        $response->assertJsonFragment(['title' => 'Fix entrance door']);
        $this->assertDatabaseHas('tasks', ['title' => 'Fix entrance door', 'created_by' => $this->manager->id]);
    }

    public function test_creating_task_with_assignee_sends_notification(): void
    {
        $assignee = User::factory()->create(['role' => 'rental_manager']);

        $this->actingAs($this->manager)
            ->postJson('/api/tasks', [
                'park_id'     => $this->park->id,
                'type'        => 'ticket',
                'title'       => 'Urgent task',
                'assigned_to' => $assignee->id,
            ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $assignee->id,
            'type'    => 'task_assigned',
        ]);
    }

    public function test_can_update_task(): void
    {
        $task = Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
            'title'      => 'Old title',
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/tasks/{$task->id}", [
                'title'    => 'New title',
                'priority' => 'urgent',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['title' => 'New title']);
    }

    public function test_can_delete_task(): void
    {
        $task = Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->deleteJson("/api/tasks/{$task->id}");

        $response->assertOk();
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_can_update_status_todo_to_in_progress(): void
    {
        $task = Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
            'status'     => 'todo',
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/tasks/{$task->id}/status", ['status' => 'in_progress']);

        $response->assertOk();
        $response->assertJsonFragment(['status' => 'in_progress']);
    }

    public function test_can_update_status_in_progress_to_done_sets_completed_at(): void
    {
        $task = Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
            'status'     => 'in_progress',
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/tasks/{$task->id}/status", ['status' => 'done']);

        $response->assertOk();
        $this->assertNotNull($response->json('completed_at'));
    }

    public function test_invalid_status_transition_returns_422(): void
    {
        $task = Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
            'status'     => 'todo',
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/tasks/{$task->id}/status", ['status' => 'done']);

        $response->assertStatus(422);
    }

    public function test_can_assign_task_and_sends_notification(): void
    {
        $task = Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
        ]);

        $assignee = User::factory()->create(['role' => 'rental_manager']);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/tasks/{$task->id}/assign", [
                'assigned_to' => $assignee->id,
            ]);

        $response->assertOk();
        $response->assertJsonPath('assigned_to.id', $assignee->id);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $assignee->id,
            'type'    => 'task_assigned',
        ]);
    }

    public function test_dashboard_returns_kanban_structure(): void
    {
        Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
            'status'     => 'todo',
        ]);
        Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
            'status'     => 'in_progress',
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/tasks/dashboard?park_id={$this->park->id}");

        $response->assertOk();
        $response->assertJsonStructure(['todo', 'in_progress']);
        $this->assertCount(1, $response->json('todo'));
        $this->assertCount(1, $response->json('in_progress'));
    }

    public function test_park_worker_dashboard_sees_only_own_tasks(): void
    {
        Task::factory()->create([
            'park_id'     => $this->park->id,
            'created_by'  => $this->manager->id,
            'assigned_to' => $this->worker->id,
            'status'      => 'todo',
        ]);
        Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
            'status'     => 'todo',
        ]);

        $response = $this->actingAs($this->worker)
            ->getJson('/api/tasks/dashboard');

        $response->assertOk();
        $this->assertCount(1, $response->json('todo'));
    }

    public function test_calendar_returns_tasks_with_due_dates(): void
    {
        Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
            'due_date'   => now()->addDays(3)->format('Y-m-d'),
        ]);
        Task::factory()->create([
            'park_id'    => $this->park->id,
            'created_by' => $this->manager->id,
            'due_date'   => null,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/tasks/calendar?park_id={$this->park->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json());
    }
}
