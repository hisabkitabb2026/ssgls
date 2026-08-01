<?php

namespace App\Http\Resources;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseItemResource extends JsonResource
{
public function toArray(Request $request): array
{
  $dateReceived = $this->date_received;
  if (is_string($dateReceived)) {
      $dateReceived = $dateReceived;
  } elseif ($dateReceived) {
      $dateReceived = $dateReceived->format('Y-m-d');
  }

  // Get consignee name from LR Receipt
  $consigneeName = null;
  if ($this->lr && $this->lr->consignee_customer_id) {
      $consignee = Customer::find($this->lr->consignee_customer_id);
      $consigneeName = $consignee?->name;
  }

  return [
      'id' => $this->id,
      'company_id' => $this->company_id,
      'lr_id' => $this->lr_id,
      'warehouse_location' => $this->warehouse_location,
      'section_name' => $this->section_name,
      'date_received' => $dateReceived,
      'destination_city' => $this->destination_city,
      'status' => $this->status,
      'notes' => $this->notes,
      'days_in_warehouse' => $this->days_in_warehouse,
      'consolidated_at' => $this->consolidation_id ? 'Yes' : 'No',
      'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
      'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
      'consignee_name' => $consigneeName,
      'lr' => $this->lr ? new InvoiceResource($this->lr) : null,
      'company' => $this->company ? new CompanyResource($this->company) : null,
  ];
}
}