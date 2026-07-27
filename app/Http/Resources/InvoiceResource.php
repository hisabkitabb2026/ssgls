<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'invoice_date' => $this->invoice_date,
            'formatted_invoice_date' => $this->formatted_invoice_date,
            'due_date' => $this->due_date,
            'formatted_due_date' => $this->formatted_due_date,
            'invoice_number' => $this->invoice_number,
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'paid_status' => $this->paid_status,
            'tax_per_item' => $this->tax_per_item,
            'tax_included' => $this->tax_included,
            'discount_per_item' => $this->discount_per_item,
            'notes' => $this->notes,
            'discount_type' => $this->discount_type,
            'discount' => $this->discount,
            'discount_val' => $this->discount_val,
            'sub_total' => $this->sub_total,
            'total' => $this->total,
            'tax' => $this->tax,
            'due_amount' => $this->due_amount,
            'sent' => $this->sent,
            'viewed' => $this->viewed,
            'unique_hash' => $this->unique_hash,
            'template_name' => $this->template_name,
            'customer_id' => $this->customer_id,
            'consignee_customer_id' => $this->consignee_customer_id,
            'recurring_invoice_id' => $this->recurring_invoice_id,
            'sequence_number' => $this->sequence_number,
            'exchange_rate' => $this->exchange_rate,
            'base_discount_val' => $this->base_discount_val,
            'base_sub_total' => $this->base_sub_total,
            'base_total' => $this->base_total,
            'creator_id' => $this->creator_id,
            'base_tax' => $this->base_tax,
            'base_due_amount' => $this->base_due_amount,
            'currency_id' => $this->currency_id,
            'invoice_pdf_url' => $this->invoicePdfUrl,
            'sales_tax_type' => $this->sales_tax_type,
            'sales_tax_address_type' => $this->sales_tax_address_type,
            'overdue' => $this->overdue,
            // CRITICAL FIX: Use whenLoaded() instead of when(exists()) to prevent N+1 queries
            // whenLoaded() only serializes if the relation was eager loaded - NO extra queries
            'items' => $this->whenLoaded('items', fn () => InvoiceItemResource::collection($this->items)),
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'consignee_customer' => $this->whenLoaded('consigneeCustomer', fn () => new CustomerResource($this->consigneeCustomer)),
            'creator' => $this->whenLoaded('creator', fn () => new UserResource($this->creator)),
            'taxes' => $this->whenLoaded('taxes', fn () => TaxResource::collection($this->taxes)),
            'fields' => $this->whenLoaded('fields', fn () => CustomFieldValueResource::collection($this->fields)),
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'currency' => $this->whenLoaded('currency', fn () => new CurrencyResource($this->currency)),
        ];
    }
}
