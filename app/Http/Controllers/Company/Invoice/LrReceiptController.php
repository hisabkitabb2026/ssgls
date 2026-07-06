<?php

namespace App\Http\Controllers\Company\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LrReceiptController extends Controller
{
    /**
     * List LR Receipts (invoices with template_name = 'lr_receipt').
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);

        $query = Invoice::whereCompany()
            ->where('template_name', 'lr_receipt')
            ->with(['customer', 'consigneeCustomer', 'items', 'currency']);

        $query->applyFilters(array_merge($request->all(), [
            'template_name' => 'lr_receipt',
        ]));

        $invoices = $query->paginate($limit);

        return response()->json([
            'data' => InvoiceResource::collection($invoices),
            'meta' => [
                'total' => $invoices->total(),
                'current_page' => $invoices->currentPage(),
                'per_page' => $invoices->perPage(),
                'last_page' => $invoices->lastPage(),
            ],
        ]);
    }

    /**
     * Show a single LR Receipt.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::whereCompany()
            ->where('template_name', 'lr_receipt')
            ->with(['customer', 'consigneeCustomer', 'items', 'items.fields', 'items.fields.customField', 'taxes', 'fields', 'currency'])
            ->findOrFail($id);

        return response()->json([
            'data' => new InvoiceResource($invoice),
        ]);
    }
}
