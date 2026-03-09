<?php

namespace Tests\Feature\Payment;

use App\Jobs\RetryPayment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Park;
use App\Models\Payment;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Park $park;
    private Customer $customer;
    private Invoice $invoice;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->park     = Park::factory()->create(['name' => 'TestPark']);
        $this->customer = Customer::factory()->create();
        $unitType       = UnitType::factory()->create(['park_id' => $this->park->id]);
        $unit           = Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $unitType->id]);
        $this->invoice  = Invoice::factory()->create([
            'customer_id'  => $this->customer->id,
            'park_id'      => $this->park->id,
            'total_amount' => 595.00,
            'status'       => 'sent',
        ]);

        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    public function test_create_payment_link(): void
    {
        Queue::fake();

        $response = $this->withToken($this->token)
            ->postJson('/api/invoices/' . $this->invoice->id . '/payment-link');

        $response->assertCreated()
            ->assertJsonStructure(['checkout_url', 'payment' => ['id', 'mollie_payment_id', 'status']]);

        $this->assertDatabaseHas('payments', [
            'invoice_id'     => $this->invoice->id,
            'payment_method' => 'mollie',
            'status'         => 'pending',
        ]);
    }

    public function test_create_payment_link_idempotent(): void
    {
        // Create first payment link
        $this->withToken($this->token)
            ->postJson('/api/invoices/' . $this->invoice->id . '/payment-link');

        // Second call returns existing pending payment
        $response = $this->withToken($this->token)
            ->postJson('/api/invoices/' . $this->invoice->id . '/payment-link');

        $response->assertOk();
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_cannot_create_payment_link_for_paid_invoice(): void
    {
        $this->invoice->update(['status' => 'paid']);

        $response = $this->withToken($this->token)
            ->postJson('/api/invoices/' . $this->invoice->id . '/payment-link');

        $response->assertStatus(422);
    }

    // --- WEBHOOK HELPERS ---

    private function mollieSignedRequest(array $payload): \Illuminate\Testing\TestResponse
    {
        $secret = 'test-mollie-secret';
        config(['services.mollie.webhook_secret' => $secret]);
        $body = json_encode($payload);
        $sig = 'sha256=' . hash_hmac('sha256', $body, $secret);
        return $this->withHeader('X-Mollie-Signature', $sig)
            ->postJson('/api/webhooks/mollie', $payload);
    }

    // --- SECURITY TESTS ---

    public function test_mollie_webhook_rejects_when_no_secret_configured(): void
    {
        config(['services.mollie.webhook_secret' => null]);

        $response = $this->postJson('/api/webhooks/mollie', ['id' => 'tr_any', 'status' => 'paid']);

        $response->assertStatus(401);
    }

    public function test_mollie_webhook_rejects_invalid_signature(): void
    {
        config(['services.mollie.webhook_secret' => 'test-mollie-secret']);

        $response = $this->withHeader('X-Mollie-Signature', 'sha256=invalidsig')
            ->postJson('/api/webhooks/mollie', ['id' => 'tr_any', 'status' => 'paid']);

        $response->assertStatus(401);
    }

    // --- FUNCTIONAL TESTS ---

    public function test_mollie_webhook_paid(): void
    {
        $payment = Payment::factory()->create([
            'invoice_id'   => $this->invoice->id,
            'amount'       => 595.00,
            'status'       => 'pending',
        ]);

        $response = $this->mollieSignedRequest([
            'id'     => $payment->mollie_payment_id,
            'status' => 'paid',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('invoices', ['id' => $this->invoice->id, 'status' => 'paid']);
    }

    public function test_mollie_webhook_idempotent(): void
    {
        $payment = Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'amount'     => 595.00,
            'status'     => 'paid',
            'paid_at'    => now(),
        ]);

        $response = $this->mollieSignedRequest([
            'id'     => $payment->mollie_payment_id,
            'status' => 'paid',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid']);
    }

    public function test_mollie_webhook_failed_queues_retry(): void
    {
        Queue::fake();

        $payment = Payment::factory()->create([
            'invoice_id'  => $this->invoice->id,
            'amount'      => 595.00,
            'status'      => 'pending',
            'retry_count' => 0,
        ]);

        $response = $this->mollieSignedRequest([
            'id'     => $payment->mollie_payment_id,
            'status' => 'failed',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'failed']);
        Queue::assertPushed(RetryPayment::class, fn ($job) => $job->paymentId === $payment->id);
    }

    public function test_mollie_webhook_failed_no_retry_when_max_reached(): void
    {
        Queue::fake();

        $payment = Payment::factory()->create([
            'invoice_id'  => $this->invoice->id,
            'amount'      => 595.00,
            'status'      => 'pending',
            'retry_count' => 3,
        ]);

        $this->mollieSignedRequest([
            'id'     => $payment->mollie_payment_id,
            'status' => 'failed',
        ]);

        Queue::assertNotPushed(RetryPayment::class);
    }

    public function test_mollie_webhook_unknown_payment_returns_ok(): void
    {
        $response = $this->mollieSignedRequest([
            'id'     => 'tr_nonexistent',
            'status' => 'paid',
        ]);

        $response->assertOk();
    }

    public function test_refund_payment(): void
    {
        $payment = Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'amount'     => 595.00,
            'status'     => 'paid',
        ]);
        $this->invoice->update(['status' => 'paid', 'paid_at' => now()]);

        $response = $this->withToken($this->token)
            ->postJson('/api/payments/' . $payment->id . '/refund');

        $response->assertOk()->assertJsonPath('message', 'Refund initiated.');
        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'refunded']);
    }

    public function test_cannot_refund_pending_payment(): void
    {
        $payment = Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'amount'     => 595.00,
            'status'     => 'pending',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/payments/' . $payment->id . '/refund');

        $response->assertStatus(422);
    }

    public function test_list_payments(): void
    {
        Payment::factory()->create([
            'invoice_id' => $this->invoice->id,
            'status'     => 'paid',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/payments');

        $response->assertOk()->assertJsonStructure(['data', 'total']);
    }

    public function test_list_payments_filter_by_status(): void
    {
        Payment::factory()->create(['invoice_id' => $this->invoice->id, 'status' => 'paid']);
        Payment::factory()->create(['invoice_id' => $this->invoice->id, 'status' => 'pending']);

        $response = $this->withToken($this->token)
            ->getJson('/api/payments?status=paid');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_list_payments_filter_by_park(): void
    {
        $otherPark    = Park::factory()->create(['name' => 'OtherPark']);
        $otherInvoice = Invoice::factory()->create(['park_id' => $otherPark->id]);
        Payment::factory()->create(['invoice_id' => $this->invoice->id]);
        Payment::factory()->create(['invoice_id' => $otherInvoice->id]);

        $response = $this->withToken($this->token)
            ->getJson('/api/payments?park_id=' . $this->park->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
