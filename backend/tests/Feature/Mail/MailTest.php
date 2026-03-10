<?php

namespace Tests\Feature\Mail;

use App\Jobs\SendMailJob;
use App\Models\Customer;
use App\Models\MailTemplate;
use App\Models\Park;
use App\Models\SentEmail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MailTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Park $park;
    private MailTemplate $template;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager  = User::factory()->create(['role' => 'main_manager']);
        $this->park     = Park::factory()->create();
        $this->template = MailTemplate::factory()->create([
            'park_id'       => null,
            'name'          => 'Welcome',
            'subject'       => 'Hello {customer_name}',
            'body_html'     => '<p>Hi {customer_name},</p>',
            'template_type' => 'welcome',
            'active'        => true,
        ]);
        $this->customer = Customer::factory()->create([
            'first_name' => 'John',
            'last_name'  => 'Doe',
            'email'      => 'john@example.com',
            'status'     => 'tenant',
        ]);
    }

    // -------------------------------------------------------------------------
    // Templates CRUD
    // -------------------------------------------------------------------------

    public function test_can_list_mail_templates(): void
    {
        $response = $this->actingAs($this->manager)->getJson('/api/mail-templates');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_filter_templates_by_type(): void
    {
        MailTemplate::factory()->create(['template_type' => 'invoice']);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/mail-templates?template_type=welcome');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_create_mail_template(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/mail-templates', [
            'name'          => 'Invoice Template',
            'subject'       => 'Invoice {invoice_number}',
            'body_html'     => '<p>Your invoice {invoice_number}</p>',
            'template_type' => 'invoice',
            'active'        => true,
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['name' => 'Invoice Template']);
        $this->assertDatabaseHas('mail_templates', ['name' => 'Invoice Template']);
    }

    public function test_can_update_mail_template(): void
    {
        $response = $this->actingAs($this->manager)
            ->putJson("/api/mail-templates/{$this->template->id}", [
                'name' => 'Updated Welcome',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Updated Welcome']);
    }

    public function test_can_delete_mail_template(): void
    {
        $response = $this->actingAs($this->manager)
            ->deleteJson("/api/mail-templates/{$this->template->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('mail_templates', ['id' => $this->template->id]);
    }

    // -------------------------------------------------------------------------
    // Preview
    // -------------------------------------------------------------------------

    public function test_can_preview_template(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/mail/preview', [
            'template_id' => $this->template->id,
            'variables'   => ['customer_name' => 'Jane'],
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['subject' => 'Hello Jane']);
        $this->assertStringContainsString('Hi Jane,', $response->json('html'));
    }

    public function test_preview_without_variables_keeps_placeholders(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/mail/preview', [
            'template_id' => $this->template->id,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('{customer_name}', $response->json('subject'));
    }

    // -------------------------------------------------------------------------
    // Send individual
    // -------------------------------------------------------------------------

    public function test_can_send_individual_email(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/mail/send', [
            'customer_id' => $this->customer->id,
            'template_id' => $this->template->id,
        ]);

        $response->assertCreated();
        $response->assertJsonFragment([
            'recipient_email' => 'john@example.com',
            'status'          => 'sent',
        ]);
        $this->assertDatabaseHas('sent_emails', [
            'customer_id' => $this->customer->id,
            'status'      => 'sent',
        ]);
    }

    public function test_send_substitutes_customer_variables(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/mail/send', [
            'customer_id' => $this->customer->id,
            'template_id' => $this->template->id,
        ]);

        $response->assertCreated();
        $this->assertStringContainsString('John Doe', $response->json('subject'));
    }

    // -------------------------------------------------------------------------
    // Mass send
    // -------------------------------------------------------------------------

    public function test_mass_send_queues_jobs_for_matching_customers(): void
    {
        Queue::fake();

        Customer::factory()->count(2)->create(['status' => 'tenant']);

        $response = $this->actingAs($this->manager)->postJson('/api/mail/mass-send', [
            'template_id'     => $this->template->id,
            'customer_status' => 'tenant',
        ]);

        $response->assertOk();
        // 1 original + 2 new = 3 tenants
        Queue::assertPushed(SendMailJob::class, 3);
        $this->assertStringContainsString('3', $response->json('message'));
    }

    public function test_mass_send_filters_by_customer_status(): void
    {
        Queue::fake();

        Customer::factory()->create(['status' => 'debtor']);

        $response = $this->actingAs($this->manager)->postJson('/api/mail/mass-send', [
            'template_id'     => $this->template->id,
            'customer_status' => 'tenant',
        ]);

        $response->assertOk();
        // Only 1 tenant (the setUp customer), not the debtor
        Queue::assertPushed(SendMailJob::class, 1);
    }

    public function test_mass_send_creates_queued_sent_email_records(): void
    {
        Queue::fake();

        $this->actingAs($this->manager)->postJson('/api/mail/mass-send', [
            'template_id'     => $this->template->id,
            'customer_status' => 'tenant',
        ]);

        $this->assertDatabaseHas('sent_emails', [
            'customer_id' => $this->customer->id,
            'status'      => 'queued',
        ]);
    }

    // -------------------------------------------------------------------------
    // Schedule
    // -------------------------------------------------------------------------

    public function test_can_schedule_mass_send(): void
    {
        Queue::fake();

        $sendAt = now()->addDays(2)->toIso8601String();

        $response = $this->actingAs($this->manager)->postJson('/api/mail/schedule', [
            'template_id'     => $this->template->id,
            'send_at'         => $sendAt,
            'customer_status' => 'tenant',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['message', 'send_at']);
        Queue::assertPushed(SendMailJob::class, 1);
    }

    public function test_schedule_rejects_past_date(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/mail/schedule', [
            'template_id' => $this->template->id,
            'send_at'     => now()->subDay()->toIso8601String(),
        ]);

        $response->assertUnprocessable();
    }

    // -------------------------------------------------------------------------
    // Sent log
    // -------------------------------------------------------------------------

    public function test_can_list_sent_emails(): void
    {
        SentEmail::factory()->count(3)->create(['sent_by' => $this->manager->id]);

        $response = $this->actingAs($this->manager)->getJson('/api/mail/sent');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_sent_by_status(): void
    {
        SentEmail::factory()->create(['status' => 'sent', 'sent_by' => $this->manager->id]);
        SentEmail::factory()->create(['status' => 'failed', 'sent_by' => $this->manager->id]);

        $response = $this->actingAs($this->manager)->getJson('/api/mail/sent?status=sent');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_unauthorized_role_cannot_access_mail(): void
    {
        $worker = User::factory()->create(['role' => 'park_worker']);

        $this->actingAs($worker)->getJson('/api/mail-templates')->assertForbidden();
    }
}
