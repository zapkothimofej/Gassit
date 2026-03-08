<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Models\SystemSetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryPayment implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $paymentId) {}

    public function handle(): void
    {
        $payment = Payment::find($this->paymentId);
        if (!$payment) {
            return;
        }

        $maxRetries = (int) (SystemSetting::where('key', 'payment_retry_max')->value('value') ?? 3);

        if ($payment->retry_count >= $maxRetries) {
            return;
        }

        // Stub: simulate a new Mollie payment attempt
        $newMollieId  = 'tr_retry_' . uniqid();
        $checkoutUrl  = 'https://sandbox.mollie.com/checkout/select-method/' . $newMollieId;

        $payment->update([
            'mollie_payment_id'   => $newMollieId,
            'mollie_checkout_url' => $checkoutUrl,
            'status'              => 'pending',
            'retry_count'         => $payment->retry_count + 1,
        ]);
    }
}
