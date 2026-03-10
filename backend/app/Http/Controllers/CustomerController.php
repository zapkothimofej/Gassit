<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Blacklist;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Traits\GeneratesSignedUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    use GeneratesSignedUrl;

    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'local');
    }

    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        if ($request->filled('park_id')) {
            $parkId = (int) $request->query('park_id');
            $query->whereHas('applications', fn ($q) => $q->where('park_id', $parkId));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');

            if (str_contains($search, '@')) {
                // Email lookup via HMAC hash
                $hash = hash_hmac('sha256', strtolower($search), config('app.key'));
                $query->where('email_hash', $hash);
            } else {
                // Use Meilisearch for encrypted fields (name, id_number)
                $scoutIds = Customer::search($search)->keys()->all();

                // Also match non-encrypted company_name in DB
                $query->where(function ($q) use ($search, $scoutIds) {
                    $q->where('company_name', 'like', "%{$search}%");
                    if (! empty($scoutIds)) {
                        $q->orWhereIn('id', $scoutIds);
                    }
                });
            }
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'            => ['required', 'in:private,company'],
            'first_name'      => ['required_if:type,private', 'nullable', 'string', 'max:255'],
            'last_name'       => ['required_if:type,private', 'nullable', 'string', 'max:255'],
            'company_name'    => ['required_if:type,company', 'nullable', 'string', 'max:255'],
            'dob'             => ['nullable', 'date'],
            'address'         => ['required', 'string', 'max:255'],
            'city'            => ['required', 'string', 'max:255'],
            'zip'             => ['required', 'string', 'max:20'],
            'country'         => ['required', 'string', 'max:100'],
            'email'           => ['required', 'email', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:50'],
            'id_number'       => ['nullable', 'string', 'max:100'],
            'tax_id'          => ['nullable', 'string', 'max:100'],
            'status'          => ['sometimes', 'in:new,tenant,not_renting,debtor,troublemaker,blacklisted'],
            'gdpr_consent_at' => ['nullable', 'date'],
            'notes'           => ['nullable', 'string'],
        ]);

        // Record GDPR consent timestamp if not explicitly provided
        if (empty($data['gdpr_consent_at'])) {
            $data['gdpr_consent_at'] = now();
        }

        $customer = Customer::create($data);

        $this->writeAuditLog($request, 'create', $customer, null, $customer->toArray());

        return response()->json($customer, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $customer = Customer::with(['contracts', 'applications'])->findOrFail($id);
        return response()->json($customer);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $old = $customer->toArray();

        $data = $request->validate([
            'type'            => ['sometimes', 'in:private,company'],
            'first_name'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'dob'             => ['sometimes', 'nullable', 'date'],
            'address'         => ['sometimes', 'string', 'max:255'],
            'city'            => ['sometimes', 'string', 'max:255'],
            'zip'             => ['sometimes', 'string', 'max:20'],
            'country'         => ['sometimes', 'string', 'max:100'],
            'email'           => ['sometimes', 'email', 'max:255'],
            'phone'           => ['sometimes', 'nullable', 'string', 'max:50'],
            'id_number'       => ['sometimes', 'nullable', 'string', 'max:100'],
            'tax_id'          => ['sometimes', 'nullable', 'string', 'max:100'],
            'status'          => ['sometimes', 'in:new,tenant,not_renting,debtor,troublemaker,blacklisted'],
            'gdpr_consent_at' => ['sometimes', 'nullable', 'date'],
            'notes'           => ['sometimes', 'nullable', 'string'],
        ]);

        $customer->update($data);

        $this->writeAuditLog($request, 'update', $customer, $old, $customer->fresh()->toArray());

        return response()->json($customer->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $old = $customer->toArray();
        $customer->delete();

        $this->writeAuditLog($request, 'delete', $customer, $old, null);

        return response()->json(['message' => 'Customer deleted.']);
    }

    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $request->validate([
            'document'      => ['required', 'file', 'mimes:pdf,jpeg,jpg,png', 'max:10240'],
            'document_type' => ['required', 'string', 'max:100'],
        ]);

        $file = $request->file('document');
        $filename = $file->getClientOriginalName();
        $path = $file->store("customers/{$id}/documents", $this->disk);

        $doc = CustomerDocument::create([
            'customer_id'   => $customer->id,
            'document_type' => $request->input('document_type'),
            'path'          => $path,
            'filename'      => $filename,
            'uploaded_by'   => $request->user()->id,
        ]);

        return response()->json($doc, 201);
    }

    public function listDocuments(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $docs = $customer->documents()->get()->map(function (CustomerDocument $doc) {
            return array_merge($doc->toArray(), [
                'download_url' => $this->signedUrl($this->disk, $doc->path),
            ]);
        });

        return response()->json($docs);
    }

    public function deleteDocument(Request $request, int $id, int $docId): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $doc = CustomerDocument::where('customer_id', $customer->id)->findOrFail($docId);

        Storage::disk($this->disk)->delete($doc->path);
        $doc->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    public function gdprDelete(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);
        $old = $customer->toArray();

        $customer->update([
            'first_name'      => '[gelöscht]',
            'last_name'       => '[gelöscht]',
            'company_name'    => null,
            'email'           => "gdpr_deleted_{$customer->id}@deleted.invalid",
            'email_hash'      => null,
            'phone'           => '[gelöscht]',
            'dob'             => null,
            'id_number'       => null,
            'address'         => '[gelöscht]',
            'city'            => '[gelöscht]',
            'zip'             => '[gelöscht]',
            'gdpr_deleted_at' => now(),
        ]);

        $this->writeAuditLog($request, 'gdpr_delete', $customer, $old, $customer->fresh()->toArray());

        return response()->json(['message' => 'Customer data anonymized.']);
    }

    public function dataExport(Request $request, int $id): JsonResponse
    {
        $customer = Customer::with([
            'applications.park',
            'applications.unitType',
            'contracts.unit.park',
            'invoices.items',
            'documents',
        ])->findOrFail($id);

        $this->writeAuditLog($request, 'data_export', $customer, null, ['exported_by' => $request->user()->id]);

        return response()->json([
            'customer'     => $customer->toArray(),
            'applications' => $customer->applications->toArray(),
            'contracts'    => $customer->contracts->toArray(),
            'invoices'     => $customer->invoices->toArray(),
            'documents'    => $customer->documents->toArray(),
            'exported_at'  => now()->toIso8601String(),
        ]);
    }

    public function blacklist(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $data = $request->validate([
            'reason' => ['required', 'string'],
        ]);

        $entry = Blacklist::create([
            'customer_id' => $customer->id,
            'reason'      => $data['reason'],
            'added_by'    => $request->user()->id,
            'added_at'    => now(),
        ]);

        $customer->update(['status' => 'blacklisted']);

        $this->writeAuditLog($request, 'blacklist', $customer, null, ['reason' => $data['reason']]);

        return response()->json($entry, 201);
    }

    public function removeBlacklist(Request $request, int $id): JsonResponse
    {
        $customer = Customer::findOrFail($id);

        $entry = Blacklist::where('customer_id', $customer->id)
            ->whereNull('removed_at')
            ->orderByDesc('added_at')
            ->firstOrFail();

        $entry->update([
            'removed_at' => now(),
            'removed_by' => $request->user()->id,
        ]);

        $customer->update(['status' => 'not_renting']);

        $this->writeAuditLog($request, 'remove_blacklist', $customer, null, ['removed_at' => now()]);

        return response()->json(['message' => 'Blacklist entry removed.']);
    }

    public function blacklistIndex(Request $request): JsonResponse
    {
        $query = Blacklist::with(['customer', 'addedBy', 'removedBy'])
            ->whereNull('removed_at');

        if ($request->filled('park_id')) {
            $parkId = (int) $request->query('park_id');
            $query->whereHas('customer.applications', fn ($q) => $q->where('park_id', $parkId));
        }

        return response()->json($query->paginate(20));
    }

    private static array $piiFields = [
        'first_name', 'last_name', 'email', 'phone', 'dob', 'address', 'id_number', 'tax_id',
    ];

    private function redactPii(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach (self::$piiFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }

    private function writeAuditLog(Request $request, string $action, Customer $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => Customer::class,
            'model_id'   => $model->id,
            'old_values' => $this->redactPii($old),
            'new_values' => $this->redactPii($new),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
