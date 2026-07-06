<?php

namespace App\Http\Controllers\Company\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\WhatsApp\OpenWaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class WhatsAppController extends Controller
{
    public function __construct(
        protected OpenWaService $whatsappService
    ) {}

    /**
     * Send invoice via WhatsApp
     */
    public function sendInvoice(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:invoices,id',
        ]);

        $invoice = Invoice::with('customer')->findOrFail($request->id);
        
        // Check if customer has phone number
        if (empty($invoice->customer->phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Customer phone number not found. Please add a phone number to the customer first.',
                'redirect' => route('customers.edit', ['id' => $invoice->customer_id]),
            ], 400);
        }

        // Format phone number for WhatsApp
        $chatId = $this->whatsappService->formatChatId($invoice->customer->phone);
        
        // Generate PDF URL
        $pdfUrl = url("/invoices/{$invoice->unique_hash}/pdf");
        
        // Get message
        $message = $this->whatsappService->getInvoiceMessage($invoice);
        
        // Send document with PDF
        $result = $this->whatsappService->sendDocument(
            $chatId,
            $pdfUrl,
            "Invoice-{$invoice->invoice_number}.pdf",
            $message
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Invoice sent successfully via WhatsApp',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to send via WhatsApp',
        ], 400);
    }

    /**
     * Send estimate via WhatsApp
     */
    public function sendEstimate(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:estimates,id',
        ]);

        $estimate = Estimate::with('customer')->findOrFail($request->id);
        
        // Check if customer has phone number
        if (empty($estimate->customer->phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Customer phone number not found. Please add a phone number to the customer first.',
                'redirect' => route('customers.edit', ['id' => $estimate->customer_id]),
            ], 400);
        }

        $chatId = $this->whatsappService->formatChatId($estimate->customer->phone);
        $pdfUrl = url("/estimates/{$estimate->unique_hash}/pdf");
        $message = $this->whatsappService->getEstimateMessage($estimate);
        
        $result = $this->whatsappService->sendDocument(
            $chatId,
            $pdfUrl,
            "Estimate-{$estimate->estimate_number}.pdf",
            $message
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Estimate sent successfully via WhatsApp',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to send via WhatsApp',
        ], 400);
    }

    /**
     * Send payment receipt via WhatsApp
     */
    public function sendPayment(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:payments,id',
        ]);

        $payment = Payment::with('customer')->findOrFail($request->id);
        
        // Check if customer has phone number
        if (empty($payment->customer->phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Customer phone number not found. Please add a phone number to the customer first.',
                'redirect' => route('customers.edit', ['id' => $payment->customer_id]),
            ], 400);
        }

        $chatId = $this->whatsappService->formatChatId($payment->customer->phone);
        $pdfUrl = url("/payments/{$payment->unique_hash}/pdf");
        $message = $this->whatsappService->getPaymentMessage($payment);
        
        $result = $this->whatsappService->sendDocument(
            $chatId,
            $pdfUrl,
            "Payment-{$payment->payment_number}.pdf",
            $message
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Payment receipt sent successfully via WhatsApp',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to send via WhatsApp',
        ], 400);
    }

    /**
     * Send report via WhatsApp
     */
    public function sendReport(Request $request): JsonResponse
    {
        $request->validate([
            'report_type' => 'required|in:profit_loss,sales,tax,expenses',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $companyId = $request->header('company');
        $company = Company::findOrFail($companyId);
        
        // Get phone from company owner
        $owner = $company->users()->whereRelation('companies', 'company_id', $companyId)->first();
        
        if (!$owner || empty($owner->phone)) {
            return response()->json([
                'success' => false,
                'message' => 'Company owner phone number not found. Please add a phone number to your profile first.',
                'redirect' => route('profile.edit'),
            ], 400);
        }

        $chatId = $this->whatsappService->formatChatId($owner->phone);
        
        // Generate report PDF URL based on type
        $pdfUrl = $this->generateReportPdfUrl(
            $request->report_type,
            $request->from_date,
            $request->to_date
        );
        
        $message = $this->whatsappService->getReportMessage(
            $request->report_type,
            $request->from_date,
            $request->to_date
        );
        
        $filename = ucwords(str_replace('_', '-', $request->report_type)) . 
                    "-{$request->from_date}-{$request->to_date}.pdf";
        
        $result = $this->whatsappService->sendDocument(
            $chatId,
            $pdfUrl,
            $filename,
            $message
        );

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Report sent successfully via WhatsApp',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to send via WhatsApp',
        ], 400);
    }

    /**
     * Generate PDF URL for report
     */
    protected function generateReportPdfUrl(string $type, string $fromDate, string $toDate): string
    {
        $baseUrl = url('/reports');
        
        return match($type) {
            'profit_loss' => "{$baseUrl}/profit-loss?from_date={$fromDate}&to_date={$toDate}&download=true",
            'sales' => "{$baseUrl}/sales?from_date={$fromDate}&to_date={$toDate}&download=true",
            'tax' => "{$baseUrl}/tax?from_date={$fromDate}&to_date={$toDate}&download=true",
            'expenses' => "{$baseUrl}/expenses?from_date={$fromDate}&to_date={$toDate}&download=true",
            default => "{$baseUrl}/profit-loss?from_date={$fromDate}&to_date={$toDate}&download=true",
        };
    }
}
