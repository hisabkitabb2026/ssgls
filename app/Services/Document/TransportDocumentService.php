<?php

namespace App\Services\Document;

use App\Models\Invoice;
use App\Services\Report\ProfitLossCalculationService;
use Illuminate\Http\Request;

class TransportDocumentService
{
public function __construct(
  private readonly DocumentItemService $documentItemService,
  private readonly ProfitLossCalculationService $profitLossService,
) {}

/**
* Determine if an invoice uses a transport template
*/
public function isTransportTemplate(Invoice $invoice): bool
{
  return in_array(
      $invoice->template_name,
      ['lorry_receipt', 'office_invoice', 'lr_receipt']
  );
}

/**
* Auto-add prefixes for transport document numbers based on template type:
* - LR Receipt: challan_no -> "CH {value}"
* - Lorry Receipt: docket_no -> "DOC {value}"
* - Office Invoice: invoice_number -> "INV {value}"
*/
public function addDocumentPrefixes(array &$data, Request $request): void
{
  $templateName = $data['template_name'] ?? $request->input('template_name', '');

  // For LR Receipt (template: lr_receipt) - add CH prefix to challan_no
  if ($templateName === 'lr_receipt' && ! empty($data['challan_no'])) {
      $challanNo = trim($data['challan_no']);
      // Only add prefix if it doesn't already start with "CH"
      if (! preg_match('/^CH[-\s]?/i', $challanNo)) {
          $data['challan_no'] = 'CH '.$challanNo;
      }
  }

  // For Lorry Receipt (template: lorry_receipt) - add DOC prefix to docket_no
  if ($templateName === 'lorry_receipt' && ! empty($data['docket_no'])) {
      $docketNo = trim($data['docket_no']);
      // Only add prefix if it doesn't already start with "DOC"
      if (! preg_match('/^DOC[-\s]?/i', $docketNo)) {
          $data['docket_no'] = 'DOC '.$docketNo;
      }
  }

  // For Office Invoice - add INV prefix to invoice_number (only if manually provided)
  if ($templateName === 'office_invoice' && $request->has('invoice_number') && ! empty($request->invoice_number)) {
      $invoiceNo = trim($data['invoice_number']);
      // Only add prefix if it doesn't already start with "INV"
      if (! preg_match('/^INV[-\s]?/i', $invoiceNo)) {
          $data['invoice_number'] = 'INV '.$invoiceNo;
      }
  }
}

/**
* Create items for transport documents (handling both regular items and custom fields)
* Transport templates may not send items — only create if present
*/
public function createItems(Invoice $invoice, Request $request): void
{
  if ($request->has('items') && ! empty($request->items)) {
      $validItems = array_filter(
          $request->items,
          fn ($item) => ! empty($item['name'])
      );

      if (! empty($validItems)) {
          $this->documentItemService->createItems($invoice, $validItems);
      }
  }
}

/**
* Update items for transport documents
*/
public function updateItems(Invoice $invoice, Request $request): void
{
  $itemIds = $invoice->items()->pluck('id');
  \App\Models\CustomFieldValue::where('custom_field_valuable_type', (new \App\Models\InvoiceItem)->getMorphClass())
      ->whereIn('custom_field_valuable_id', $itemIds)
      ->delete();

  $invoice->items()->delete();

  $this->createItems($invoice, $request);
}

/**
* Create taxes for transport documents
*/
public function createTaxes(Invoice $invoice, Request $request): void
{
  if ($request->has('taxes') && ! empty($request->taxes)) {
      $this->documentItemService->createTaxes($invoice, $request->taxes);
  }
}

/**
* Recalculate profit/loss for transport templates
*/
public function recalculateProfitLoss(Invoice $invoice): void
{
  if ($invoice->template_name === 'office_invoice') {
      $this->profitLossService->recalculateFromOfficeInvoice($invoice);
  } elseif ($invoice->template_name === 'lorry_receipt') {
      $this->profitLossService->recalculateFromLorryReceipt($invoice);
  }
}
}