<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LlmAccessCode;
use App\Models\Park;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LlmAccessCodeController extends Controller
{
    public function index(int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);

        $codes = LlmAccessCode::where('park_id', $parkId)
            ->orderBy('valid_from')
            ->get();

        return response()->json($codes);
    }

    public function store(Request $request, int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);

        $data = $request->validate([
            'code'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'valid_from'  => ['required', 'date'],
            'valid_to'    => ['nullable', 'date', 'after_or_equal:valid_from'],
            'active'      => ['nullable', 'boolean'],
        ]);

        $data['park_id'] = $parkId;
        $data['active']  = $data['active'] ?? true;

        $code = LlmAccessCode::create($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'llm_access_code_created',
            'model_type' => LlmAccessCode::class,
            'model_id'   => $code->id,
            'old_values' => null,
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($code, 201);
    }

    public function update(Request $request, int $parkId, int $id): JsonResponse
    {
        Park::findOrFail($parkId);

        $code = LlmAccessCode::where('park_id', $parkId)->findOrFail($id);

        $data = $request->validate([
            'code'        => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'valid_from'  => ['sometimes', 'date'],
            'valid_to'    => ['nullable', 'date'],
            'active'      => ['sometimes', 'boolean'],
        ]);

        $old = $code->toArray();
        $code->update($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'llm_access_code_updated',
            'model_type' => LlmAccessCode::class,
            'model_id'   => $code->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($code);
    }

    public function destroy(Request $request, int $parkId, int $id): JsonResponse
    {
        Park::findOrFail($parkId);

        $code = LlmAccessCode::where('park_id', $parkId)->findOrFail($id);
        $old  = $code->toArray();

        $code->delete();

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'llm_access_code_deleted',
            'model_type' => LlmAccessCode::class,
            'model_id'   => $id,
            'old_values' => json_encode($old),
            'new_values' => null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Access code deleted']);
    }

    public function sync(Request $request, int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);

        $activeCodes = LlmAccessCode::where('park_id', $parkId)
            ->where('active', true)
            ->where('valid_from', '<=', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now()->toDateString());
            })
            ->get();

        // LLM lock adapter stub — in production, call the lock system's REST API
        $synced = $activeCodes->map(fn ($c) => ['code' => $c->code, 'valid_from' => $c->valid_from, 'valid_to' => $c->valid_to]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'llm_access_codes_synced',
            'model_type' => Park::class,
            'model_id'   => $parkId,
            'old_values' => null,
            'new_values' => json_encode(['synced_count' => $activeCodes->count()]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'synced'  => $synced,
            'message' => 'Active codes pushed to LLM lock system',
        ]);
    }
}
