<?php

declare(strict_types=1);

namespace Tests\Feature\Invoice;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceAuthorizationTest extends TestCase
{
use RefreshDatabase;

private Company $company;

private Company $otherCompany;

private User $user;

private User $otherUser;

protected function setUp(): void
{
  parent::setUp();
  $this->artisan('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
  $this->company = Company::factory()->create();
  $this->otherCompany = Company::factory()->create();
  $this->user = User::factory()->create();
  $this->otherUser = User::factory()->create();

  $this->user->companies()->attach($this->company);
  $this->otherUser->companies()->attach($this->otherCompany);
}

public function test_user_cannot_access_other_company_invoices(): void
{
  $invoice = Invoice::factory()
      ->for($this->otherCompany)
      ->create();

  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->getJson("/api/v1/invoices/{$invoice->id}");

  $response->assertStatus(404);
}

public function test_user_cannot_update_other_company_invoices(): void
{
  $invoice = Invoice::factory()
      ->for($this->otherCompany)
      ->create();

  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->patchJson("/api/v1/invoices/{$invoice->id}", [
          'invoice_number' => 'INV-999',
      ]);

  $response->assertStatus(404);
}

public function test_user_cannot_delete_other_company_invoices(): void
{
  $invoice = Invoice::factory()
      ->for($this->otherCompany)
      ->create();

  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->deleteJson("/api/v1/invoices/{$invoice->id}");

  $response->assertStatus(404);
}

public function test_unauthenticated_user_cannot_access_invoices(): void
{
  $response = $this->getJson('/api/v1/invoices');

  $response->assertStatus(401);
}

public function test_user_can_only_see_own_company_invoices(): void
{
  $ownInvoice = Invoice::factory()
      ->for($this->company)
      ->create();

  $otherInvoice = Invoice::factory()
      ->for($this->otherCompany)
      ->create();

  $response = $this->actingAs($this->user)
      ->withHeader('company', (string) $this->company->id)
      ->getJson('/api/v1/invoices');

  $response->assertStatus(200);
  $ids = collect($response->json('data'))->pluck('id')->toArray();
  $this->assertContains($ownInvoice->id, $ids);
  $this->assertNotContains($otherInvoice->id, $ids);
}
}