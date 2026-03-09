<?php

namespace App\Http\Controllers;

use App\Jobs\RetryPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

class PaymentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Payment::with(['invoice.park', 'invoice.customer']);

        if ($request->filled('park_id')) {
            $query->whereHas('invoice', fn ($q) => $q->where('park_id', $request->query('park_id')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('method')) {
            $query->where('payment_method', $request->query('method'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function createPaymentLink(Request $request, int $invoiceId): JsonResponse
    {
        $invoice = Invoice::findOrFail($invoiceId);

        if (in_array($invoice->status, ['paid', 'cancelled'])) {
            return response()->json(['message' => 'Invoice cannot accept payments.'], 422);
        }

        // Idempotency: return existing pending payment link if already created
        $existing = $invoice->payments()->where('status', 'pending')->where('payment_method', 'mollie')->first();
        if ($existing) {
            return response()->json([
                'checkout_url' => $existing->mollie_checkout_url,
                'payment'      => $existing,
            ]);
        }

        // Stub: create Mollie payment
        $molliePaymentId = 'tr_' . uniqid();
        $checkoutUrl     = 'https://sandbox.mollie.com/checkout/select-method/' . $molliePaymentId;

        $payment = Payment::create([
            'invoice_id'          => $invoice->id,
            'amount'              => $invoice->total_amount,
            'currency'            => 'EUR',
            'payment_method'      => 'mollie',
            'mollie_payment_id'   => $molliePaymentId,
            'mollie_checkout_url' => $checkoutUrl,
            'status'              => 'pending',
            'retry_count'         => 0,
        ]);

        return response()->json([
            'checkout_url' => $checkoutUrl,
            'payment'      => $payment,
        ], 201);
    }

    public function mollieWebhook(Request $request): JsonResponse
    {
        // Verify Mollie signature if secret is configured
        $mollieSecret = config('services.mollie.webhook_secret');
        if ($mollieSecret) {
            $signature  = $request->header('X-Mollie-Signature');
            $payload    = $request->getContent();
            $expected   = 'sha256=' . hash_hmac('sha256', $payload, $mollieSecret);
            if (!$signature || !hash_equals($expected, $signature)) {
                return response()->json(['message' => 'Invalid signature.'], 401);
            }
        }

        $mollieId = $request->input('id');
        if (!$mollieId) {
            return response()->json(['message' => 'Missing payment id.'], 422);
        }

        $payment = Payment::where('mollie_payment_id', $mollieId)->first();
        if (!$payment) {
            // Unknown payment — acknowledge silently (Mollie expects 200)
            return response()->json(['message' => 'ok']);
        }

        // Idempotency: already processed
        if (in_array($payment->status, ['paid', 'refunded', 'cancelled'])) {
            return response()->json(['message' => 'ok']);
        }

        // Stub: determine status from webhook payload
        $status = $request->input('status', 'paid');

        if ($status === 'paid') {
            $payment->update(['status' => 'paid', 'paid_at' => now()]);

            $invoice = $payment->invoice;
            $totalPaid = $invoice->payments()->where('status', 'paid')->sum('amount');
            if ($totalPaid >= $invoice->total_amount) {
                $invoice->update(['status' => 'paid', 'paid_at' => now()]);
            } elseif ($totalPaid > 0) {
                $invoice->update(['status' => 'partially_paid']);
            }
        } elseif ($status === 'failed') {
            $maxRetries = (int) (SystemSetting::where('key', 'payment_retry_max')->value('value') ?? 3);
            $payment->update(['status' => 'failed', 'failure_reason' => $request->input('failure_reason', 'Payment failed')]);

            if ($payment->retry_count < $maxRetries) {
                RetryPayment::dispatch($payment->id)->delay(now()->addHours(24));
            }
        } elseif ($status === 'cancelled') {
            $payment->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'ok']);
    }

    public function refund(Request $request, int $paymentId): JsonResponse
    {
        $payment = Payment::findOrFail($paymentId);

        if ($payment->status !== 'paid') {
            return response()->json(['message' => 'Only paid payments can be refunded.'], 422);
        }

        // Stub: initiate Mollie refund
        $payment->update(['status' => 'refunded']);

        $invoice = $payment->invoice;
        $paidPayments = $invoice->payments()->where('status', 'paid')->count();
        if ($paidPayments === 0) {
            $invoice->update(['status' => 'overdue', 'paid_at' => null]);
        }

        return response()->json(['message' => 'Refund initiated.', 'payment' => $payment->fresh()]);
    }
}
