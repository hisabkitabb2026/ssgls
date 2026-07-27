<?php

namespace App\Http\Controllers\Company\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Requests;
use App\Http\Requests\DeleteInvoiceRequest;
use App\Http\Requests\SendInvoiceRequest;
use App\Http\Resources\EstimateResource;
use App\Http\Resources\InvoiceResource;
use App\Jobs\GenerateInvoicePdfJob;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Services\Document\InvoiceService;
use App\Services\Document\SerialNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Markdown;

class InvoicesController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $limit = $request->input('limit', 10);

        $baseQuery = Invoice::whereCompany();

        // Exclude all transport receipt templates (lr_receipt, lorry_receipt,
        // office_invoice) when no template_name is specified - this makes the
        // standard Invoices list show only standard invoice templates (invoice1,
        // invoice2, etc.). When a specific template_name IS provided (e.g.
        // office_invoice for Invoice Receipts, lorry_receipt, lr_receipt), only
        // that template's invoices are returned.
        if (! $request->filled('template_name')) {
            $baseQuery->whereNotIn('template_name', ['lr_receipt', 'lorry_receipt', 'office_invoice']);
        }

        $invoices = $baseQuery
            ->applyFilters($request->all())
            ->with(['customer', 'consigneeCustomer'])
            ->latest()
            ->paginateData($limit);

        // When limit=all, paginateData returns a Collection (not a Paginator),
        // so we can't call ->total() or ->additional() on it.
        if ($limit === 'all') {
            $totalCount = $invoices->count();

            return InvoiceResource::collection($invoices)
                ->additional(['meta' => [
                    'invoice_total_count' => $totalCount,
                ]]);
        }

        // Use cached count from paginator metadata instead of separate count() query
        // This is much faster as it uses the same query as the pagination
        $totalCount = $invoices->total();

        return InvoiceResource::collection($invoices)
            ->additional(['meta' => [
                'invoice_total_count' => $totalCount,
            ]]);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Requests\InvoicesRequest $request)
    {
        $this->authorize('create', Invoice::class);

        $invoice = $this->invoiceService->create($request);

        if ($request->has('invoiceSend')) {
            $this->invoiceService->send($invoice, $request->only(['subject', 'body']));
        }

        GenerateInvoicePdfJob::dispatch($invoice);

        return new InvoiceResource($invoice);
    }

    /**
     * Display the specified resource.
     *
     * @return JsonResponse
     */
    public function show(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function update(Requests\InvoicesRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $invoice = $this->invoiceService->update($invoice, $request);

        GenerateInvoicePdfJob::dispatch($invoice, true);

        return new InvoiceResource($invoice);
    }

    /**
     * delete the specified resources in storage.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function delete(DeleteInvoiceRequest $request)
    {
        $this->authorize('delete multiple invoices');

        $ids = Invoice::whereCompany()
            ->whereIn('id', $request->ids)
            ->pluck('id');

        $this->invoiceService->delete($ids);

        return response()->json([
            'success' => true,
        ]);
    }

    public function send(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $this->invoiceService->send($invoice, $request->all());

        return response()->json([
            'success' => true,
        ]);
    }

    public function sendPreview(SendInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $markdown = new Markdown(view(), config('mail.markdown'));

        $data = $this->invoiceService->sendInvoiceData($invoice, $request->all());
        $data['url'] = $invoice->invoicePdfUrl;

        return $markdown->render('emails.send.invoice', ['data' => $data]);
    }

    public function clone(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $this->authorize('create', Invoice::class);

        $newInvoice = $this->invoiceService->clone($invoice);

        return new InvoiceResource($newInvoice);
    }

    public function convertToEstimate(Request $request, Invoice $invoice)
    {
        // Authorize access to the source invoice (tenant isolation) in addition
        // to the ability to create an estimate.
        $this->authorize('view', $invoice);
        $this->authorize('create', Estimate::class);

        $estimate = $this->invoiceService->convertToEstimate($invoice);

        return new EstimateResource($estimate);
    }

    public function changeStatus(Request $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $this->invoiceService->changeStatus($invoice, $request->status);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Find an invoice by its invoice_number (e.g. LR Receipt Docket No).
     * Used by the Office Invoice form to auto-fill Consignment Details
     * when the user enters a Consignment No that matches an existing
     * LR Receipt's Docket No.
     *
     * @return JsonResponse
     */
    public function findByInvoiceNumber(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $request->validate([
            'invoice_number' => 'required|string',
            'template_name' => 'nullable|string',
        ]);

        $invoiceNumber = $request->input('invoice_number');
        $templateName = $request->input('template_name', 'lr_receipt');

        $invoice = Invoice::whereCompany()
            ->where('invoice_number', $invoiceNumber)
            ->where('template_name', $templateName)
            ->with(['customer', 'consigneeCustomer', 'fields', 'items.fields.customField', 'currency'])
            ->first();

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new InvoiceResource($invoice),
        ]);
    }

    /**
     * Get the next invoice number based on company settings format.
     *
     * @return JsonResponse
     */
    public function getNextNumber(Request $request)
    {
        $this->authorize('create', Invoice::class);

        $serial = (new SerialNumberService)
            ->setModel(Invoice::class)
            ->setCompany($request->header('company'))
            ->setCustomer($request->get('customer_id'))
            ->setTemplateName($request->get('template_name'))
            ->setNextNumbers();

        $nextNumber = $serial->getNextNumber();

        return response()->json([
            'success' => true,
            'data' => [
                'next_number' => $nextNumber,
            ],
        ]);
    }
}
