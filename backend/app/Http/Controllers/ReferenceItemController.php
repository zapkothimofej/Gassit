<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ReferenceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ReferenceItem::orderBy('sort_order')->orderBy('label');

        if ($request->filled('category')) {
            $query->where('category', $request->query('category'));
        }

        if ($request->filled('active')) {
            $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category'   => ['required', 'string', 'in:country,city,document_type,termination_reason,damage_category,unit_feature,industry_sector'],
            'value'      => ['required', 'string', 'max:255'],
            'label'      => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active'     => ['nullable', 'boolean'],
        ]);

        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['active']     = $data['active'] ?? true;

        $item = ReferenceItem::create($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'reference_item_created',
            'model_type' => ReferenceItem::class,
            'model_id'   => $item->id,
            'old_values' => null,
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($item, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = ReferenceItem::findOrFail($id);

        $data = $request->validate([
            'category'   => ['sometimes', 'string', 'in:country,city,document_type,termination_reason,damage_category,unit_feature,industry_sector'],
            'value'      => ['sometimes', 'string', 'max:255'],
            'label'      => ['sometimes', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active'     => ['sometimes', 'boolean'],
        ]);

        $old = $item->toArray();
        $item->update($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'reference_item_updated',
            'model_type' => ReferenceItem::class,
            'model_id'   => $item->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($item);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = ReferenceItem::findOrFail($id);
        $old = $item->toArray();

        $item->update(['active' => false]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'reference_item_deactivated',
            'model_type' => ReferenceItem::class,
            'model_id'   => $item->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode(['active' => false]),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Reference item deactivated']);
    }
}
