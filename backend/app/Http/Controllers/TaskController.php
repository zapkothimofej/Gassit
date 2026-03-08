<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Task::with(['assignedTo', 'createdBy', 'park']);

        if ($request->filled('park_id')) {
            $query->where('park_id', $request->query('park_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->query('assigned_to'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->query('priority'));
        }

        // park_worker only sees own tasks
        if ($user->role === 'park_worker') {
            $query->where('assigned_to', $user->id);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'park_id'      => ['required', 'integer', 'exists:parks,id'],
            'type'         => ['required', 'string', 'in:application,damage,ticket,general,inspection,renewal'],
            'title'        => ['required', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'assigned_to'  => ['nullable', 'integer', 'exists:users,id'],
            'due_date'     => ['nullable', 'date'],
            'priority'     => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'related_type' => ['nullable', 'string'],
            'related_id'   => ['nullable', 'integer'],
        ]);

        $task = Task::create(array_merge($data, [
            'created_by' => $request->user()->id,
            'status'     => 'todo',
        ]));

        if (!empty($data['assigned_to'])) {
            Notification::create([
                'user_id'      => $data['assigned_to'],
                'type'         => 'task_assigned',
                'title'        => 'New task assigned',
                'body'         => 'You have been assigned to task: ' . $task->title,
                'related_type' => Task::class,
                'related_id'   => $task->id,
            ]);
        }

        return response()->json($task->load(['assignedTo', 'createdBy']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $data = $request->validate([
            'type'         => ['nullable', 'string', 'in:application,damage,ticket,general,inspection,renewal'],
            'title'        => ['nullable', 'string', 'max:255'],
            'description'  => ['nullable', 'string'],
            'assigned_to'  => ['nullable', 'integer', 'exists:users,id'],
            'due_date'     => ['nullable', 'date'],
            'priority'     => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'related_type' => ['nullable', 'string'],
            'related_id'   => ['nullable', 'integer'],
        ]);

        $old = $task->toArray();
        $task->update($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'task_updated',
            'model_type' => Task::class,
            'model_id'   => $task->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($data),
        ]);

        return response()->json($task->fresh()->load(['assignedTo', 'createdBy']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $task = Task::findOrFail($id);
        $task->delete();

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'task_deleted',
            'model_type' => Task::class,
            'model_id'   => $id,
            'old_values' => json_encode(['id' => $id]),
            'new_values' => null,
        ]);

        return response()->json(['message' => 'Task deleted.']);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $task = Task::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:todo,in_progress,done,cancelled'],
        ]);

        $transitions = [
            'todo'        => ['in_progress', 'cancelled'],
            'in_progress' => ['done', 'todo', 'cancelled'],
            'done'        => [],
            'cancelled'   => [],
        ];

        if (!in_array($data['status'], $transitions[$task->status] ?? [])) {
            return response()->json([
                'message' => "Cannot transition from '{$task->status}' to '{$data['status']}'.",
            ], 422);
        }

        $update = ['status' => $data['status']];
        if ($data['status'] === 'done') {
            $update['completed_at'] = now();
        }

        $task->update($update);

        return response()->json($task->fresh());
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $task = Task::findOrFail($id);

        $data = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $task->update(['assigned_to' => $data['assigned_to']]);

        Notification::create([
            'user_id'      => $data['assigned_to'],
            'type'         => 'task_assigned',
            'title'        => 'Task assigned to you',
            'body'         => 'You have been assigned to task: ' . $task->title,
            'related_type' => Task::class,
            'related_id'   => $task->id,
        ]);

        return response()->json($task->fresh()->load('assignedTo'));
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Task::with(['assignedTo', 'createdBy'])
            ->whereNotIn('status', ['done', 'cancelled']);

        if ($user->role === 'park_worker') {
            $query->where('assigned_to', $user->id);
        } elseif ($request->filled('park_id')) {
            $query->where('park_id', $request->query('park_id'));
        }

        $tasks = $query->get();

        $grouped = [
            'todo'        => $tasks->where('status', 'todo')->values(),
            'in_progress' => $tasks->where('status', 'in_progress')->values(),
        ];

        return response()->json($grouped);
    }

    public function calendar(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Task::with(['assignedTo'])
            ->whereNotNull('due_date');

        if ($user->role === 'park_worker') {
            $query->where('assigned_to', $user->id);
        } elseif ($request->filled('park_id')) {
            $query->where('park_id', $request->query('park_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('due_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('due_date', '<=', $request->query('to'));
        }

        return response()->json($query->orderBy('due_date')->get());
    }
}
