<?php

declare(strict_types=1);

namespace Tests\Feature\Transport;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LorryReceiptTest extends TestCase
{
use RefreshDatabase;

private Company $company;

private User $user;

protected function setUp(): void
{
  parent::setUp();
  $this->artisan('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
  $this->company = Company::factory()->create();
  $this->user = User::factory()->create();
  $this->user->companies()->attach($this->company);
}

public function test_can_create_lorry_receipt(): void
{
  $payload = [
      'template_name' => 'lorry_receipt',
      'docket_no' => 'DOC-001',
      'vehicle_number' => 'KA-01-AB-1234',
      'from_code' => 'FROM001',
      'from_name' => 'Bangalore',
      'to_code' => 'TO001',
      'to_name' => 'Delhi',
      'truck_no' => 'TRUCK001',
      'mode_of_payment' => 'CASH',
      'gst_tax_payable_by' => 'CONSIGNEE',
      'description_of_goods' => 'Electronics',
      'hsn_code' => '8470',
      'eway_bill_no' => 'EWAY001',
      'actual_weight' => 1000,
      'charged_weight' => 1000,
      'no_of_articles' => 10,
      'packing' => 'BOX',
  ];

  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->postJson('/api/v1/invoices', $payload);

  $response->assertStatus(201);
  $this->assertDatabaseHas('invoices', [
      'template_name' => 'lorry_receipt',
      'docket_no' => 'DOC-001',
  ]);
}

public function test_can_update_lorry_receipt(): void
{
  $invoice = Invoice::factory()
      ->for($this->company)
      ->create(['template_name' => 'lorry_receipt']);

  $payload = [
      'docket_no' => 'DOC-002',
      'vehicle_number' => 'KA-01-CD-5678',
  ];

  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->patchJson("/api/v1/invoices/{$invoice->id}", $payload);

  $response->assertStatus(200);
  $this->assertDatabaseHas('invoices', [
      'id' => $invoice->id,
      'docket_no' => 'DOC-002',
      'vehicle_number' => 'KA-01-CD-5678',
  ]);
}

public function test_lorry_receipt_requires_transport_fields(): void
{
  $payload = [
      'template_name' => 'lorry_receipt',
      'docket_no' => 'DOC-001',
      // Missing required fields
  ];

  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->postJson('/api/v1/invoices', $payload);

  $response->assertStatus(422);
  $response->assertJsonValidationErrors(['vehicle_number', 'from_code', 'to_code']);
}

public function test_can_delete_lorry_receipt(): void
{
  $invoice = Invoice::factory()
      ->for($this->company)
      ->create(['template_name' => 'lorry_receipt']);

  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->deleteJson("/api/v1/invoices/{$invoice->id}");

  $response->assertStatus(200);
  $this->assertModelMissing($invoice);
}

public function test_can_retrieve_lorry_receipt_with_related_data(): void
{
  $invoice = Invoice::factory()
      ->for($this->company)
      ->hasItems(3)
      ->create(['template_name' => 'lorry_receipt']);

  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->getJson("/api/v1/invoices/{$invoice->id}");

  $response->assertStatus(200)
      ->assertJsonPath('data.template_name', 'lorry_receipt')
      ->assertJsonPath('data.items.@count', 3);
}

public function test_lorry_receipt_authorization(): void
{
  $otherUser = User::factory()->create();
  $invoice = Invoice::factory()
      ->for($this->company)
      ->create(['template_name' => 'lorry_receipt']);

  $response = $this->actingAs($otherUser)
      ->withHeader('company', (string) $this->company->id)
      ->patchJson("/api/v1/invoices/{$invoice->id}", ['docket_no' => 'DOC-002']);

  $response->assertStatus(403);
}
}