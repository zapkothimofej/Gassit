<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\DocumentTemplate;
use App\Models\Park;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Park $park;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->park  = Park::factory()->create();
    }

    public function test_can_list_document_templates(): void
    {
        DocumentTemplate::create([
            'park_id'       => null,
            'name'          => 'Rental Contract v1',
            'document_type' => 'rental_contract',
            'template_html' => '<p>Contract for {customer_name}</p>',
            'variables_json'=> ['customer_name'],
            'version'       => 1,
            'active'        => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/document-templates');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_filter_by_park(): void
    {
        DocumentTemplate::create([
            'park_id' => $this->park->id, 'name' => 'Park Template',
            'document_type' => 'invoice', 'template_html' => '<p>Invoice</p>',
            'variables_json' => [], 'version' => 1, 'active' => true,
        ]);
        DocumentTemplate::create([
            'park_id' => null, 'name' => 'Global Template',
            'document_type' => 'invoice', 'template_html' => '<p>Invoice</p>',
            'variables_json' => [], 'version' => 1, 'active' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/document-templates?park_id=' . $this->park->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Park Template', $response->json('data.0.name'));
    }

    public function test_can_filter_by_document_type(): void
    {
        DocumentTemplate::create([
            'park_id' => null, 'name' => 'Contract',
            'document_type' => 'rental_contract', 'template_html' => '<p>c</p>',
            'variables_json' => [], 'version' => 1, 'active' => true,
        ]);
        DocumentTemplate::create([
            'park_id' => null, 'name' => 'Invoice',
            'document_type' => 'invoice', 'template_html' => '<p>i</p>',
            'variables_json' => [], 'version' => 1, 'active' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/document-templates?document_type=rental_contract');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_create_document_template(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/document-templates', [
            'name'          => 'Invoice Template',
            'document_type' => 'invoice',
            'template_html' => '<p>Invoice {invoice_number}</p>',
            'variables_json'=> ['invoice_number'],
            'version'       => 1,
            'active'        => true,
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['name' => 'Invoice Template']);
        $this->assertDatabaseHas('document_templates', ['name' => 'Invoice Template']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_template_created']);
    }

    public function test_create_validates_document_type(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/document-templates', [
            'name'          => 'Bad',
            'document_type' => 'invalid_type',
            'template_html' => '<p>x</p>',
        ]);

        $response->assertUnprocessable();
    }

    public function test_can_update_document_template(): void
    {
        $template = DocumentTemplate::create([
            'name' => 'Old Name', 'document_type' => 'invoice',
            'template_html' => '<p>x</p>', 'variables_json' => [],
            'version' => 1, 'active' => true,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/document-templates/{$template->id}", [
            'name' => 'New Name',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'New Name']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_template_updated']);
    }

    public function test_can_clone_document_template(): void
    {
        $template = DocumentTemplate::create([
            'name' => 'Contract v1', 'document_type' => 'rental_contract',
            'template_html' => '<p>Contract</p>', 'variables_json' => ['customer_name'],
            'version' => 1, 'active' => true,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/document-templates/{$template->id}/clone");

        $response->assertCreated();
        $response->assertJsonFragment(['version' => 2]);
        $response->assertJsonFragment(['active' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document_template_cloned']);
    }

    public function test_clone_increments_version(): void
    {
        DocumentTemplate::create([
            'name' => 'Contract v1', 'document_type' => 'rental_contract',
            'template_html' => '<p>v1</p>', 'variables_json' => [],
            'version' => 1, 'active' => true,
        ]);
        $v2 = DocumentTemplate::create([
            'name' => 'Contract v2', 'document_type' => 'rental_contract',
            'template_html' => '<p>v2</p>', 'variables_json' => [],
            'version' => 2, 'active' => true,
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/document-templates/{$v2->id}/clone");

        $response->assertCreated();
        $response->assertJsonFragment(['version' => 3]);
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/document-templates')->assertUnauthorized();
    }

    public function test_requires_admin_or_main_manager_role(): void
    {
        $worker = User::factory()->create(['role' => 'park_worker']);
        $this->actingAs($worker)->getJson('/api/document-templates')->assertForbidden();
    }
}
