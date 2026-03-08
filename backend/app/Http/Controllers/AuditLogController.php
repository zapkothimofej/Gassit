<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::with('user')
            ->when($request->query('user_id'), fn($q, $v) => $q->where('user_id', $v))
            ->when($request->query('model_type'), fn($q, $v) => $q->where('model_type', $v))
            ->when($request->query('model_id'), fn($q, $v) => $q->where('model_id', $v))
            ->when($request->query('from'), fn($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->query('to'), fn($q, $v) => $q->where('created_at', '<=', $v . ' 23:59:59'))
            ->orderByDesc('created_at');

        return response()->json($query->paginate(50));
    }

    public function show(int $id)
    {
        $log = AuditLog::with('user')->findOrFail($id);

        return response()->json($log);
    }

    public function export(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date',
        ]);

        $logs = AuditLog::with('user')
            ->when($request->query('from'), fn($q, $v) => $q->where('created_at', '>=', $v))
            ->when($request->query('to'), fn($q, $v) => $q->where('created_at', '<=', $v . ' 23:59:59'))
            ->orderByDesc('created_at')
            ->get();

        $rows   = [];
        $rows[] = implode(',', ['id', 'user_id', 'user_name', 'action', 'model_type', 'model_id', 'ip_address', 'created_at']);

        foreach ($logs as $log) {
            $rows[] = implode(',', [
                $log->id,
                $log->user_id ?? '',
                $log->user ? '"' . addslashes($log->user->name) . '"' : '',
                '"' . addslashes((string) $log->action) . '"',
                '"' . addslashes((string) $log->model_type) . '"',
                $log->model_id ?? '',
                $log->ip_address ?? '',
                $log->created_at,
            ]);
        }

        $csv = implode("\n", $rows);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-logs.csv"',
        ]);
    }
}
