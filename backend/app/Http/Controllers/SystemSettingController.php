<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(SystemSetting::orderBy('key')->get());
    }

    public function show(string $key): JsonResponse
    {
        $setting = SystemSetting::where('key', $key)->firstOrFail();
        return response()->json($setting);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings'             => ['required', 'array'],
            'settings.*.key'       => ['required', 'string'],
            'settings.*.value'     => ['required', 'string'],
        ]);

        $updated = [];
        foreach ($data['settings'] as $item) {
            $setting = SystemSetting::where('key', $item['key'])->first();
            if (!$setting) {
                continue;
            }
            $old = $setting->value;
            $setting->update(['value' => $item['value']]);

            AuditLog::create([
                'user_id'    => $request->user()->id,
                'action'     => 'system_setting_updated',
                'model_type' => SystemSetting::class,
                'model_id'   => $setting->id,
                'old_values' => json_encode(['value' => $old]),
                'new_values' => json_encode(['value' => $item['value']]),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $updated[] = $setting;
        }

        return response()->json($updated);
    }
}
