<?php

namespace App\Http\Controllers;

use App\Jobs\SendMailJob;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\MailTemplate;
use App\Models\SentEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailController extends Controller
{
    // -------------------------------------------------------------------------
    // Mail Templates CRUD
    // -------------------------------------------------------------------------

    public function templatesIndex(Request $request): JsonResponse
    {
        $query = MailTemplate::with('park');

        if ($request->filled('park_id')) {
            $query->where('park_id', $request->query('park_id'));
        }

        if ($request->filled('template_type')) {
            $query->where('template_type', $request->query('template_type'));
        }

        if ($request->filled('active')) {
            $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->orderBy('name')->paginate(20));
    }

    public function templatesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'park_id'       => ['nullable', 'integer', 'exists:parks,id'],
            'name'          => ['required', 'string', 'max:255'],
            'subject'       => ['required', 'string', 'max:500'],
            'body_html'     => ['required', 'string'],
            'template_type' => ['required', 'string', 'in:welcome,contract,invoice,dunning_1,dunning_2,dunning_3,termination,deposit_return,waiting_list,custom'],
            'variables_json'=> ['nullable', 'array'],
            'active'        => ['nullable', 'boolean'],
        ]);

        $data['active'] = $data['active'] ?? true;
        $template = MailTemplate::create($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'mail_template_created',
            'model_type' => MailTemplate::class,
            'model_id'   => $template->id,
            'old_values' => null,
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
        ]);

        return response()->json($template, 201);
    }

    public function templatesUpdate(Request $request, int $id): JsonResponse
    {
        $template = MailTemplate::findOrFail($id);

        $data = $request->validate([
            'park_id'       => ['nullable', 'integer', 'exists:parks,id'],
            'name'          => ['nullable', 'string', 'max:255'],
            'subject'       => ['nullable', 'string', 'max:500'],
            'body_html'     => ['nullable', 'string'],
            'template_type' => ['nullable', 'string', 'in:welcome,contract,invoice,dunning_1,dunning_2,dunning_3,termination,deposit_return,waiting_list,custom'],
            'variables_json'=> ['nullable', 'array'],
            'active'        => ['nullable', 'boolean'],
        ]);

        $old = $template->toArray();
        $template->update($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'mail_template_updated',
            'model_type' => MailTemplate::class,
            'model_id'   => $template->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($data),
            'ip_address' => $request->ip(),
        ]);

        return response()->json($template->fresh());
    }

    public function templatesDestroy(Request $request, int $id): JsonResponse
    {
        $template = MailTemplate::findOrFail($id);
        $template->delete();

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'mail_template_deleted',
            'model_type' => MailTemplate::class,
            'model_id'   => $id,
            'old_values' => json_encode(['id' => $id]),
            'new_values' => null,
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'Template deleted.']);
    }

    // -------------------------------------------------------------------------
    // Preview
    // -------------------------------------------------------------------------

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'exists:mail_templates,id'],
            'variables'   => ['nullable', 'array'],
        ]);

        $template = MailTemplate::findOrFail($data['template_id']);
        $variables = $data['variables'] ?? [];

        $html = $this->substitute($template->body_html, $variables);
        $subject = $this->substitute($template->subject, $variables);

        return response()->json([
            'subject' => $subject,
            'html'    => $html,
        ]);
    }

    // -------------------------------------------------------------------------
    // Send individual email
    // -------------------------------------------------------------------------

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'  => ['required', 'integer', 'exists:customers,id'],
            'template_id'  => ['required', 'integer', 'exists:mail_templates,id'],
            'variables'    => ['nullable', 'array'],
        ]);

        $customer = Customer::findOrFail($data['customer_id']);
        $template = MailTemplate::findOrFail($data['template_id']);
        $variables = array_merge($this->customerVariables($customer), $data['variables'] ?? []);

        $html = $this->substitute($template->body_html, $variables);
        $subject = $this->substitute($template->subject, $variables);

        $sentEmail = SentEmail::create([
            'customer_id'     => $customer->id,
            'recipient_email' => $customer->email,
            'subject'         => $subject,
            'body_html'       => $html,
            'template_id'     => $template->id,
            'sent_by'         => $request->user()->id,
            'status'          => 'sent',
            'sent_at'         => now(),
        ]);

        return response()->json($sentEmail, 201);
    }

    // -------------------------------------------------------------------------
    // Mass send
    // -------------------------------------------------------------------------

    public function massSend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_id'       => ['required', 'integer', 'exists:mail_templates,id'],
            'park_id'           => ['nullable', 'integer', 'exists:parks,id'],
            'customer_status'   => ['nullable', 'string'],
            'contract_status'   => ['nullable', 'string'],
            'variables'         => ['nullable', 'array'],
        ]);

        $template = MailTemplate::findOrFail($data['template_id']);
        $query = Customer::query();

        if (!empty($data['park_id'])) {
            $query->whereHas('applications', fn($q) => $q->where('park_id', $data['park_id']));
        }

        if (!empty($data['customer_status'])) {
            $query->where('status', $data['customer_status']);
        }

        if (!empty($data['contract_status'])) {
            $query->whereHas('contracts', fn($q) => $q->where('status', $data['contract_status']));
        }

        $customers = $query->get();
        $extraVars = $data['variables'] ?? [];
        $queued = 0;

        foreach ($customers as $customer) {
            $variables = array_merge($this->customerVariables($customer), $extraVars);
            $html = $this->substitute($template->body_html, $variables);
            $subject = $this->substitute($template->subject, $variables);

            $sentEmail = SentEmail::create([
                'customer_id'     => $customer->id,
                'recipient_email' => $customer->email,
                'subject'         => $subject,
                'body_html'       => $html,
                'template_id'     => $template->id,
                'sent_by'         => $request->user()->id,
                'status'          => 'queued',
                'sent_at'         => null,
            ]);

            SendMailJob::dispatch($sentEmail->id);
            $queued++;
        }

        return response()->json(['message' => "Queued {$queued} emails."]);
    }

    // -------------------------------------------------------------------------
    // Schedule mass send
    // -------------------------------------------------------------------------

    public function schedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_id'       => ['required', 'integer', 'exists:mail_templates,id'],
            'send_at'           => ['required', 'date', 'after:now'],
            'park_id'           => ['nullable', 'integer', 'exists:parks,id'],
            'customer_status'   => ['nullable', 'string'],
            'contract_status'   => ['nullable', 'string'],
            'variables'         => ['nullable', 'array'],
        ]);

        $sendAt = \Carbon\Carbon::parse($data['send_at']);
        $template = MailTemplate::findOrFail($data['template_id']);
        $query = Customer::query();

        if (!empty($data['park_id'])) {
            $query->whereHas('applications', fn($q) => $q->where('park_id', $data['park_id']));
        }

        if (!empty($data['customer_status'])) {
            $query->where('status', $data['customer_status']);
        }

        if (!empty($data['contract_status'])) {
            $query->whereHas('contracts', fn($q) => $q->where('status', $data['contract_status']));
        }

        $customers = $query->get();
        $extraVars = $data['variables'] ?? [];
        $scheduled = 0;

        foreach ($customers as $customer) {
            $variables = array_merge($this->customerVariables($customer), $extraVars);
            $html = $this->substitute($template->body_html, $variables);
            $subject = $this->substitute($template->subject, $variables);

            $sentEmail = SentEmail::create([
                'customer_id'     => $customer->id,
                'recipient_email' => $customer->email,
                'subject'         => $subject,
                'body_html'       => $html,
                'template_id'     => $template->id,
                'sent_by'         => $request->user()->id,
                'status'          => 'queued',
                'sent_at'         => null,
            ]);

            SendMailJob::dispatch($sentEmail->id)->delay($sendAt);
            $scheduled++;
        }

        return response()->json([
            'message'    => "Scheduled {$scheduled} emails.",
            'send_at'    => $sendAt->toIso8601String(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Sent log
    // -------------------------------------------------------------------------

    public function sent(Request $request): JsonResponse
    {
        $query = SentEmail::with(['customer', 'template']);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->query('customer_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('template_id')) {
            $query->where('template_id', $request->query('template_id'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function substitute(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }
        return $text;
    }

    private function customerVariables(Customer $customer): array
    {
        return [
            'customer_name'      => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
            'customer_email'     => $customer->email ?? '',
            'customer_phone'     => $customer->phone ?? '',
            'customer_address'   => $customer->address ?? '',
        ];
    }
}
