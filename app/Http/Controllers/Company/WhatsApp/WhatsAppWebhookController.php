<?php

namespace App\Http\Controllers\Company\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\LorryReceipt;
use App\Models\Payment;
use App\Services\WhatsApp\OpenWaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected OpenWaService $whatsappService
    ) {}

    /**
     * Handle incoming WhatsApp webhook from OpenWa
     * This endpoint receives inbound messages from customers
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        Log::info('WhatsApp Webhook received', ['payload' => $request->all()]);

        // OpenWa webhook payload structure:
        // {
        //     "event": "message",
        //     "sessionId": "default",
        //     "data": {
        //         "from": "919876543210@c.us",
        //         "message": "INV-001" or "Invoice 123" or "Challan 456",
        //         "timestamp": "2024-01-01T10:00:00Z"
        //     }
        // }

        $event = $request->input('event');
        
        if ($event !== 'message') {
            return response()->json(['success' => true, 'message' => 'Event ignored']);
        }

        $data = $request->input('data', []);
        $from = $data['from'] ?? null;
        $messageText = trim($data['message'] ?? '');

        if (!$from || !$messageText) {
            Log::warning('WhatsApp webhook: Missing from or message');
            return response()->json(['success' => false, 'message' => 'Invalid payload'], 400);
        }

        // Extract company from session or use default
        $sessionId = $request->input('sessionId', 'default');
        $company = $this->findCompanyByWhatsAppSession($sessionId);
        
        if (!$company) {
            Log::warning('WhatsApp webhook: Company not found for session', ['sessionId' => $sessionId]);
            return response()->json(['success' => false, 'message' => 'Company not found'], 404);
        }

        // Set company context
        $request->merge(['company' => $company->id]);
        $request->headers->set('company', $company->id);

        // Parse the message to find document number
        $result = $this->parseAndFindDocument($messageText, $company->id, $from);

        if ($result['found']) {
            // Send the document back
            $this->sendDocumentBack($result['type'], $result['document'], $from);
            
            return response()->json([
                'success' => true,
                'message' => 'Document sent successfully',
            ]);
        }

        // Document not found - send help message
        $this->sendHelpMessage($from, $company);

        return response()->json([
            'success' => true,
            'message' => 'Document not found, help sent',
        ]);
    }

    /**
     * Parse message text and search for document
     */
    protected function parseAndFindDocument(string $message, int $companyId, string $fromPhone): array
    {
        $message = strtoupper(trim($message));

        // Patterns to match:
        // "INV-001", "INVOICE 001", "INV001", "001"
        // "CHALLAN 123", "CHALLAN-123"
        // "DOCKET 456", "DOCKET-456"
        // "LR 789", "LR-789", "LR RECEIPT 789"
        // "ESTIMATE 100", "EST-100"
        // "PAYMENT 200", "PAY-200"

        $patterns = [
            'invoice' => [
                '/^(?:INV|INVOICE)[-\s]?(\d+)$/i',
                '/^(\d+)$/', // Just a number - assume invoice
            ],
            'challan' => [
                '/^(?:CHALLAN|CH)[-\s]?(\d+)$/i',
            ],
            'docket' => [
                '/^(?:DOCKET|DKT)[-\s]?(\d+)$/i',
            ],
            'lr_receipt' => [
                '/^(?:LR|LRR)[-\s]?(\d+)$/i',
                '/^(?:LR[-\s]?RECEIPT)[-\s]?(\d+)$/i',
            ],
            'lorry_receipt' => [
                '/^(?:LORRY|LRY)[-\s]?(\d+)$/i',
            ],
            'estimate' => [
                '/^(?:EST|ESTIMATE)[-\s]?(\d+)$/i',
            ],
            'payment' => [
                '/^(?:PAY|PAYMENT)[-\s]?(\d+)$/i',
            ],
        ];

        foreach ($patterns as $type => $regexList) {
            foreach ($regexList as $regex) {
                if (preg_match($regex, $message, $matches)) {
                    $number = $matches[1];
                    $document = $this->searchDocument($type, $number, $companyId, $fromPhone);
                    
                    if ($document) {
                        return [
                            'found' => true,
                            'type' => $type,
                            'document' => $document,
                        ];
                    }
                }
            }
        }

        // If no pattern matched, try searching all types with the raw message
        $rawNumber = preg_replace('/[^0-9A-Z-]/', '', $message);
        
        foreach (['invoice', 'challan', 'docket', 'lr_receipt', 'lorry_receipt', 'estimate', 'payment'] as $type) {
            $document = $this->searchDocument($type, $rawNumber, $companyId, $fromPhone);
            if ($document) {
                return [
                    'found' => true,
                    'type' => $type,
                    'document' => $document,
                ];
            }
        }

        return ['found' => false];
    }

    /**
     * Search for document by type and number
     * Searches both with and without prefixes (CH, DOC, INV) to handle user input variations
     */
    protected function searchDocument(string $type, string $number, int $companyId, string $fromPhone)
    {
        $query = null;

        switch ($type) {
            case 'invoice':
            case 'challan':
            case 'docket':
            case 'lr_receipt':
            case 'lorry_receipt':
                // For invoices, check invoice_number, challan_no, docket_no, lr_number
                // Search both with prefix (CH 123, DOC 123, INV 123) and without (123)
                $query = Invoice::where('company_id', $companyId)
                    ->where(function ($q) use ($number) {
                        // Search exact match
                        $q->where('invoice_number', $number)
                          ->orWhere('challan_no', $number)
                          ->orWhere('docket_no', $number)
                          ->orWhere('lr_number', $number)
                          // Search with prefixes added (user typed "123", DB has "CH 123")
                          ->orWhere('challan_no', 'CH ' . $number)
                          ->orWhere('docket_no', 'DOC ' . $number)
                          ->orWhere('invoice_number', 'INV ' . $number)
                          // Search without prefixes (user typed "CH 123", DB has "123" or "CH 123")
                          ->orWhereRaw('REPLACE(invoice_number, "INV ", "") = ?', [$number])
                          ->orWhereRaw('REPLACE(challan_no, "CH ", "") = ?', [$number])
                          ->orWhereRaw('REPLACE(docket_no, "DOC ", "") = ?', [$number])
                          // Search without dashes
                          ->orWhereRaw('REPLACE(invoice_number, "-", "") = ?', [$number])
                          ->orWhereRaw('REPLACE(challan_no, "-", "") = ?', [$number])
                          ->orWhereRaw('REPLACE(docket_no, "-", "") = ?', [$number])
                          ->orWhereRaw('REPLACE(lr_number, "-", "") = ?', [$number]);
                    })
                    ->with('customer');
                break;

            case 'estimate':
                $query = Estimate::where('company_id', $companyId)
                    ->where(function ($q) use ($number) {
                        $q->where('estimate_number', $number)
                          ->orWhereRaw('REPLACE(estimate_number, "-", "") = ?', [$number]);
                    })
                    ->with('customer');
                break;

            case 'payment':
                $query = Payment::where('company_id', $companyId)
                    ->where(function ($q) use ($number) {
                        $q->where('payment_number', $number)
                          ->orWhereRaw('REPLACE(payment_number, "-", "") = ?', [$number]);
                    })
                    ->with('customer');
                break;
        }

        if ($query) {
            $document = $query->first();
            
            if ($document) {
                // Verify the phone number matches the customer
                $customerPhone = $this->normalizePhone($document->customer->phone ?? '');
                $fromPhoneNormalized = $this->normalizePhone($fromPhone);
                
                // Allow if phone matches OR if we can't verify (fallback for testing)
                if ($customerPhone && $fromPhoneNormalized && $customerPhone !== $fromPhoneNormalized) {
                    Log::warning('Phone mismatch', [
                        'document' => $type,
                        'number' => $number,
                        'from' => $fromPhone,
                        'customer_phone' => $document->customer->phone,
                    ]);
                    return null; // Don't send to wrong number
                }
            }
            
            return $document;
        }

        return null;
    }

    /**
     * Send document back via WhatsApp
     */
    protected function sendDocumentBack(string $type, $document, string $fromPhone): void
    {
        $chatId = $this->whatsappService->formatChatId($fromPhone);
        
        switch ($type) {
            case 'invoice':
            case 'challan':
            case 'docket':
            case 'lr_receipt':
            case 'lorry_receipt':
                $pdfUrl = url("/invoices/{$document->unique_hash}/pdf");
                $filename = "Invoice-{$document->invoice_number}.pdf";
                $message = $this->whatsappService->getInvoiceMessage($document);
                break;

            case 'estimate':
                $pdfUrl = url("/estimates/{$document->unique_hash}/pdf");
                $filename = "Estimate-{$document->estimate_number}.pdf";
                $message = $this->whatsappService->getEstimateMessage($document);
                break;

            case 'payment':
                $pdfUrl = url("/payments/{$document->unique_hash}/pdf");
                $filename = "Payment-{$document->payment_number}.pdf";
                $message = $this->whatsappService->getPaymentMessage($document);
                break;

            default:
                Log::error('Unknown document type', ['type' => $type]);
                return;
        }

        $result = $this->whatsappService->sendDocument($chatId, $pdfUrl, $filename, $message);

        if ($result['success']) {
            Log::info('Document sent via WhatsApp', [
                'type' => $type,
                'number' => $document->invoice_number ?? $document->estimate_number ?? $document->payment_number,
                'to' => $fromPhone,
            ]);
        } else {
            Log::error('Failed to send document via WhatsApp', [
                'type' => $type,
                'error' => $result['error'],
            ]);
        }
    }

    /**
     * Send help message when document not found
     */
    protected function sendHelpMessage(string $fromPhone, Company $company): void
    {
        $chatId = $this->whatsappService->formatChatId($fromPhone);
        $companyName = CompanySetting::getSetting('company_name', $company->id) ?? 'Our Company';

        $message = "📋 *{$companyName}* - Document Lookup\n\n" .
                   "To get your document, simply reply with:\n\n" .
                   "• Invoice Number (e.g., `INV-001` or `001`)\n" .
                   "• Challan Number (e.g., `CHALLAN-123`)\n" .
                   "• Docket Number (e.g., `DOCKET-456`)\n" .
                   "• LR Receipt Number (e.g., `LR-789`)\n" .
                   "• Estimate Number (e.g., `EST-100`)\n" .
                   "• Payment Number (e.g., `PAY-200`)\n\n" .
                   "We'll send you the PDF right away! 📄";

        $this->whatsappService->sendText($chatId, $message);
    }

    /**
     * Find company by WhatsApp session ID
     */
    protected function findCompanyByWhatsAppSession(string $sessionId): ?Company
    {
        // For now, use the first company with WhatsApp enabled
        // In production, you might want to map sessions to companies
        return Company::whereHas('settings', function ($query) use ($sessionId) {
            $query->where('name', 'whatsapp_enabled')
                  ->where('value', 'true');
        })->first();
    }

    /**
     * Normalize phone number for comparison
     */
    protected function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove @c.us if present
        $phone = str_replace('@c.us', '', $phone);
        
        return $phone;
    }
}
