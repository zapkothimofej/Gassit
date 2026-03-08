<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DocumentTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DocumentTemplate::with('park');

        if ($request->filled('park_id')) {
            $query->where('park_id', $request->query('park_id'));
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->query('document_type'));
        }

        if ($request->filled('active')) {
            $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->orderBy('name')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'park_id'       => ['nullable', 'integer', 'exists:parks,id'],
            'name'          => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'in:rental_contract,invoice,termination_letter,dunning_letter,deposit_return,welcome_letter'],
            'template_html' => ['required', 'string'],
            'variables_json'=> ['nullable', 'array'],
            'version'       => ['nullable', 'integer', 'min:1'],
            'active'        => ['nullable', 'boolean'],
        ]);

        $data['version'] = $data['version'] ?? 1;
        $data['active']  = $data['active'] ?? true;

        $template = DocumentTemplate::create($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'document_template_created',
            'model_type' => DocumentTemplate::class,
            'model_id'   => $template->id,
            'old_values' => null,
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($template->load('park'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = DocumentTemplate::findOrFail($id);

        $data = $request->validate([
            'park_id'       => ['nullable', 'integer', 'exists:parks,id'],
            'name'          => ['sometimes', 'string', 'max:255'],
            'document_type' => ['sometimes', 'string', 'in:rental_contract,invoice,termination_letter,dunning_letter,deposit_return,welcome_letter'],
            'template_html' => ['sometimes', 'string'],
            'variables_json'=> ['nullable', 'array'],
            'version'       => ['sometimes', 'integer', 'min:1'],
            'active'        => ['sometimes', 'boolean'],
        ]);

        $old = $template->toArray();
        $template->update($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'document_template_updated',
            'model_type' => DocumentTemplate::class,
            'model_id'   => $template->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($template->load('park'));
    }

    public function clone(Request $request, int $id): JsonResponse
    {
        $original = DocumentTemplate::findOrFail($id);

        $newVersion = DocumentTemplate::where('document_type', $original->document_type)
            ->when($original->park_id, fn ($q) => $q->where('park_id', $original->park_id))
            ->max('version') + 1;

        $cloneData = [
            'park_id'       => $original->park_id,
            'name'          => $original->name . ' (v' . $newVersion . ')',
            'document_type' => $original->document_type,
            'template_html' => $original->template_html,
            'variables_json'=> $original->variables_json,
            'version'       => $newVersion,
            'active'        => false,
        ];

        $clone = DocumentTemplate::create($cloneData);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'document_template_cloned',
            'model_type' => DocumentTemplate::class,
            'model_id'   => $clone->id,
            'old_values' => json_encode(['cloned_from' => $original->id]),
            'new_values' => json_encode($cloneData),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($clone->load('park'), 201);
    }
}
