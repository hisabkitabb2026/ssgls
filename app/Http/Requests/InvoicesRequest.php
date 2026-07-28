<?php

namespace App\Http\Requests;

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Rules\ConsignmentNumberExists;
use App\Support\DocumentTotals;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoicesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.s
     */
    public function rules(): array
    {
        // Transport receipt templates (lr_receipt, lorry_receipt, office_invoice)
        // use native transport columns instead of line items, and LR receipts
        // don't require a customer.
        $isTransportTemplate = in_array(
            $this->template_name,
            ['lr_receipt', 'lorry_receipt', 'office_invoice'],
        );

        $rules = [
            'invoice_date' => [
                'required',
            ],
            'due_date' => [
                'nullable',
            ],
            'customer_id' => [
                $isTransportTemplate && in_array($this->template_name, ['lr_receipt', 'lorry_receipt'])
                    ? 'nullable'
                    : 'required',
            ],
            'invoice_number' => [
                'required',
                Rule::unique('invoices')
                    ->where('company_id', $this->header('company'))
                    ->where('template_name', $this->template_name),
            ],
            'exchange_rate' => [
                'nullable',
            ],
            'discount' => $isTransportTemplate
                ? ['nullable']
                : ['numeric', 'required'],
            'discount_val' => $isTransportTemplate
                ? ['nullable']
                : ['integer', 'required'],
            'sub_total' => $isTransportTemplate
                ? ['nullable']
                : ['numeric', 'required'],
            'total' => $isTransportTemplate
                ? ['nullable']
                : ['numeric', 'max:999999999999', 'required'],
            'tax' => $isTransportTemplate
                ? ['nullable']
                : ['required'],
            'template_name' => [
                'required',
            ],
        ];

        if (! $isTransportTemplate) {
            $rules['items'] = ['required', 'array'];
            $rules['items.*'] = ['required', 'max:255'];
            $rules['items.*.description'] = ['nullable'];
            $rules['items.*.name'] = ['required'];
            $rules['items.*.quantity'] = ['numeric', 'required'];
            $rules['items.*.price'] = ['numeric', 'required'];
        }

        // Office Invoice: each consignment item must have a consignment number
        if ($this->template_name === 'office_invoice') {
            $rules['items'] = ['required', 'array'];
            $rules['items.*.consignment_number'] = ['required', 'string', 'max:255', new ConsignmentNumberExists];
        }

        $companyCurrency = CompanySetting::getSetting('currency', $this->header('company'));

        $customer = Customer::find($this->customer_id);

        if ($customer && $companyCurrency) {
            if ((string) $customer->currency_id !== $companyCurrency) {
                $rules['exchange_rate'] = [
                    'required',
                ];
            }
        }

        if ($this->isMethod('PUT')) {
            $rules['invoice_number'] = [
                'required',
                Rule::unique('invoices')
                    ->ignore($this->route('invoice')->id)
                    ->where('company_id', $this->header('company'))
                    ->where('template_name', $this->template_name),
            ];
        }

        return $rules;
    }

    public function getInvoicePayload(): array
    {
        $company_currency = CompanySetting::getSetting('currency', $this->header('company'));
        $current_currency = $this->currency_id;
        $exchange_rate = $company_currency != $current_currency ? $this->exchange_rate : 1;

        // Transport receipt templates may not have a customer, so resolve the
        // currency safely — falling back to the company currency.
        $customer = Customer::find($this->customer_id);
        $currency = $customer ? $customer->currency_id : $company_currency;

        $tax_per_item = CompanySetting::getSetting('tax_per_item', $this->header('company')) ?? 'NO ';
        $discount_per_item = CompanySetting::getSetting('discount_per_item', $this->header('company')) ?? 'NO';

        // Recompute the document totals server-side from the line items so a
        // tampered total/sub_total/tax/due_amount in the request is ignored
        // (GHSA-8c69). Transport receipt templates send no items, so the
        // computed totals will all be zero — which is correct.
        $totals = DocumentTotals::compute(
            $this->items ?? [],
            $this->taxes ?? [],
            $this->discount_val ?? 0,
            $tax_per_item,
            (bool) $this->tax_included,
            $discount_per_item
        );

        $payload = collect($this->except('items', 'taxes'))
            ->merge([
                'creator_id' => $this->user()->id ?? null,
                'status' => $this->has('invoiceSend') ? Invoice::STATUS_SENT : Invoice::STATUS_DRAFT,
                'paid_status' => Invoice::STATUS_UNPAID,
                'company_id' => $this->header('company'),
                'tax_per_item' => $tax_per_item,
                'discount_per_item' => $discount_per_item,
                'sub_total' => $totals['sub_total'],
                'total' => $totals['total'],
                'tax' => $totals['tax'],
                'due_amount' => $totals['total'],
                'sent' => (bool) $this->sent ?? false,
                'viewed' => (bool) $this->viewed ?? false,
                'exchange_rate' => $exchange_rate,
                'base_total' => $totals['total'] * $exchange_rate,
                'base_discount_val' => ($this->discount_val ?? 0) * $exchange_rate,
                'base_sub_total' => $totals['sub_total'] * $exchange_rate,
                'base_tax' => $totals['tax'] * $exchange_rate,
                'base_due_amount' => $totals['total'] * $exchange_rate,
                'currency_id' => $currency,
            ]);

        // Transport fields (from_code, to_code, truck_no, advance_amount, etc.)
        // are now sent as top-level fields and are included via $this->except()
        // above. No custom field mapping is needed — the Invoice model uses
        // $guarded = ['id'] so all transport columns are mass-assignable.

        return $payload->toArray();
    }
}
