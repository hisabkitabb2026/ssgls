<?php

use App\Models\CustomField;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $this->companyId = $user->companies()->first()->id;

    $this->withHeaders([
        'company' => $this->companyId,
    ]);

    Sanctum::actingAs($user, ['*']);
});

test('lr receipt custom fields are auto-created on first fetch', function () {
    $response = getJson('api/v1/custom-fields?template_name=lr_receipt&limit=all');

    $response->assertOk();

    // Verify Invoice-level fields were created
    $invoiceFields = CustomField::where('company_id', $this->companyId)
        ->where('model_type', 'Invoice')
        ->where('slug', 'LIKE', 'CUSTOM_Invoice_%')
        ->get();

    $invoiceFieldNames = $invoiceFields->pluck('name')->toArray();

    expect($invoiceFieldNames)->toContain('Time')
        ->and($invoiceFieldNames)->toContain('From')
        ->and($invoiceFieldNames)->toContain('To')
        ->and($invoiceFieldNames)->toContain('Truck No')
        ->and($invoiceFieldNames)->toContain('Consignor')
        ->and($invoiceFieldNames)->toContain('Consignee')
        ->and($invoiceFieldNames)->toContain('Mode of Payment')
        ->and($invoiceFieldNames)->toContain('GST Tax Payable By');

    // Verify Item-level fields were created
    $itemFields = CustomField::where('company_id', $this->companyId)
        ->where('model_type', 'Item')
        ->where('slug', 'LIKE', 'CUSTOM_Item_%')
        ->get();

    $itemFieldNames = $itemFields->pluck('name')->toArray();

    expect($itemFieldNames)->toContain('Description of Goods')
        ->and($itemFieldNames)->toContain('HSN Code')
        ->and($itemFieldNames)->toContain('Basic Freight')
        ->and($itemFieldNames)->toContain('Docket Charge')
        ->and($itemFieldNames)->toContain('FOV');
});

test('lorry receipt custom fields are auto-created on first fetch', function () {
    $response = getJson('api/v1/custom-fields?template_name=lorry_receipt&limit=all');

    $response->assertOk();

    // Verify Invoice-level fields were created
    $invoiceFields = CustomField::where('company_id', $this->companyId)
        ->where('model_type', 'Invoice')
        ->where('slug', 'LIKE', 'CUSTOM_Invoice_%')
        ->get();

    $invoiceFieldNames = $invoiceFields->pluck('name')->toArray();

    expect($invoiceFieldNames)->toContain('From')
        ->and($invoiceFieldNames)->toContain('To')
        ->and($invoiceFieldNames)->toContain('Lorry No')
        ->and($invoiceFieldNames)->toContain('Owner Name')
        ->and($invoiceFieldNames)->toContain('Driver Name')
        ->and($invoiceFieldNames)->toContain('Broker Name')
        ->and($invoiceFieldNames)->toContain('Lorry Hire')
        ->and($invoiceFieldNames)->toContain('Advance Paid Rs')
        ->and($invoiceFieldNames)->toContain('Final Balance Amount Paid at');
});

test('office invoice custom fields are auto-created on first fetch', function () {
    $response = getJson('api/v1/custom-fields?template_name=office_invoice&limit=all');

    $response->assertOk();

    // Verify Invoice-level fields were created
    $invoiceFields = CustomField::where('company_id', $this->companyId)
        ->where('model_type', 'Invoice')
        ->where('slug', 'LIKE', 'CUSTOM_Invoice_%')
        ->get();

    $invoiceFieldNames = $invoiceFields->pluck('name')->toArray();

    expect($invoiceFieldNames)->toContain('GST Tax Through');

    // Verify Item-level fields were created
    $itemFields = CustomField::where('company_id', $this->companyId)
        ->where('model_type', 'Item')
        ->where('slug', 'LIKE', 'CUSTOM_Item_%')
        ->get();

    $itemFieldNames = $itemFields->pluck('name')->toArray();

    expect($itemFieldNames)->toContain('Consignment Number')
        ->and($itemFieldNames)->toContain('Consignment Date')
        ->and($itemFieldNames)->toContain('Rate')
        ->and($itemFieldNames)->toContain('LR Charge')
        ->and($itemFieldNames)->toContain('DD Charge');
});

test('lorry receipt fields have no default answers', function () {
    getJson('api/v1/custom-fields?template_name=lorry_receipt&limit=all');

    $lorryFields = CustomField::where('company_id', $this->companyId)
        ->where('slug', 'LIKE', 'CUSTOM_Invoice_%')
        ->get();

    // Lorry receipt fields should have null default answers
    $lorryFields->each(function ($field) {
        expect($field->default_answer)->toBeNull();
    });
});

test('lr receipt docket charge has default answer of 100', function () {
    getJson('api/v1/custom-fields?template_name=lr_receipt&limit=all');

    $docketCharge = CustomField::where('company_id', $this->companyId)
        ->where('model_type', 'Item')
        ->where('name', 'Docket Charge')
        ->first();

    expect($docketCharge)->not->toBeNull()
        ->and($docketCharge->default_answer)->toBe(100);

});

test('mode of payment field has dropdown options', function () {
    getJson('api/v1/custom-fields?template_name=lr_receipt&limit=all');

    $modeOfPayment = CustomField::where('company_id', $this->companyId)
        ->where('name', 'Mode of Payment')
        ->first();

    expect($modeOfPayment)->not->toBeNull()
        ->and($modeOfPayment->type)->toBe('Dropdown')
        ->and($modeOfPayment->options)->not->toBeNull();

    $optionNames = collect($modeOfPayment->options)->pluck('name')->toArray();
    expect($optionNames)->toContain('TO PAY')
        ->and($optionNames)->toContain('PAID')
        ->and($optionNames)->toContain('TO BE BILLED AT');
});
