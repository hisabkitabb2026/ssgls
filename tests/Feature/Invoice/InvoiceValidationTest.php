<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceValidationTest extends TestCase
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

public function test_invoice_requires_customer_id(): void
{
  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->postJson('/api/v1/invoices', [
          'invoice_number' => 'INV-001',
      ]);

  $response->assertStatus(422);
  $response->assertJsonValidationErrors(['customer_id']);
}

public function test_invoice_requires_invoice_number(): void
{
  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->postJson('/api/v1/invoices', [
          'customer_id' => 1,
      ]);

  $response->assertStatus(422);
  $response->assertJsonValidationErrors(['invoice_number']);
}

public function test_lorry_receipt_requires_transport_fields(): void
{
  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->postJson('/api/v1/invoices', [
          'template_name' => 'lorry_receipt',
          'invoice_number' => 'DOC-001',
      ]);

  $response->assertStatus(422);
  $response->assertJsonValidationErrors([
      'vehicle_number',
      'from_code',
      'to_code',
  ]);
}

public function test_lr_receipt_doesnt_require_customer_id(): void
{
  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->postJson('/api/v1/invoices', [
          'template_name' => 'lr_receipt',
          'invoice_number' => 'LR-001',
          'from_code' => 'FROM001',
          'from_name' => 'Bangalore',
          'to_code' => 'TO001',
          'to_name' => 'Delhi',
      ]);

  // Should not require customer_id for LR receipt
  $response->assertStatus(201);
}

public function test_invalid_template_name_rejected(): void
{
  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->postJson('/api/v1/invoices', [
          'template_name' => 'invalid_template',
          'customer_id' => 1,
          'invoice_number' => 'INV-001',
      ]);

  $response->assertStatus(422);
  $response->assertJsonValidationErrors(['template_name']);
}

public function test_duplicate_invoice_number_rejected(): void
{
  $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->postJson('/api/v1/invoices', [
          'customer_id' => 1,
          'invoice_number' => 'INV-001',
      ]);

  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->postJson('/api/v1/invoices', [
          'customer_id' => 1,
          'invoice_number' => 'INV-001',
      ]);

  $response->assertStatus(422);
  $response->assertJsonValidationErrors(['invoice_number']);
}
}