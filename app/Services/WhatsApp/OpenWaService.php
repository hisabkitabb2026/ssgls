<?php

namespace App\Services\WhatsApp;

use App\Models\CompanySetting;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenWaService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected string $sessionId;
    protected bool $enabled;
    protected ?int $companyId;

    public function __construct()
    {
        $this->companyId = request()->header('company');
        
        $this->enabled = CompanySetting::getSetting('whatsapp_enabled', $this->companyId) === 'true';
        $this->baseUrl = CompanySetting::getSetting('whatsapp_server_url', $this->companyId) 
            ?? config('services.whatsapp.base_url', 'http://localhost:2785');
        $this->apiKey = CompanySetting::getSetting('whatsapp_api_key', $this->companyId) 
            ?? config('services.whatsapp.api_key', '');
        $this->sessionId = CompanySetting::getSetting('whatsapp_session_id', $this->companyId) 
            ?? 'default';
    }

    /**
     * Check if WhatsApp is enabled and configured
     */
    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    /**
     * Send text message via WhatsApp
     */
    public function sendText(string $chatId, string $message): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'WhatsApp is not enabled or configured'];
        }

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/sessions/{$this->sessionId}/messages/send-text", [
                'chatId' => $chatId,
                'text' => $message,
            ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('WhatsApp send text failed: ' . $response->body());
            return ['success' => false, 'error' => $response->body()];

        } catch (\Exception $e) {
            Log::error('WhatsApp send text exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send document (PDF) via WhatsApp
     */
    public function sendDocument(string $chatId, string $documentUrl, string $filename, string $caption = ''): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'error' => 'WhatsApp is not enabled or configured'];
        }

        try {
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/sessions/{$this->sessionId}/messages/send-document", [
                'chatId' => $chatId,
                'url' => $documentUrl,
                'filename' => $filename,
                'caption' => $caption,
            ]);

            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }

            Log::error('WhatsApp send document failed: ' . $response->body());
            return ['success' => false, 'error' => $response->body()];

        } catch (\Exception $e) {
            Log::error('WhatsApp send document exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Format phone number for WhatsApp (919876543210@c.us)
     */
    public function formatChatId(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present (default to India +91)
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }
        
        return $phone . '@c.us';
    }

    /**
     * Get formatted invoice message
     */
    public function getInvoiceMessage(Invoice $invoice): string
    {
        $companyName = CompanySetting::getSetting('company_name', $invoice->company_id) ?? 'Our Company';
        $currency = CompanySetting::getSetting('currency', $invoice->company_id) ?? '₹';
        
        return "📄 *Invoice from {$companyName}*\n\n" .
               "*Invoice No:* {$invoice->invoice_number}\n" .
               "*Amount:* {$currency} " . number_format($invoice->base_total, 2) . "\n" .
               "*Due Date:* " . $invoice->due_date->format('d-M-Y') . "\n\n" .
               "Please find the attached PDF for details.";
    }

    /**
     * Get formatted estimate message
     */
    public function getEstimateMessage($estimate): string
    {
        $companyName = CompanySetting::getSetting('company_name', $estimate->company_id) ?? 'Our Company';
        $currency = CompanySetting::getSetting('currency', $estimate->company_id) ?? '₹';
        
        return "📋 *Estimate from {$companyName}*\n\n" .
               "*Estimate No:* {$estimate->estimate_number}\n" .
               "*Amount:* {$currency} " . number_format($estimate->base_total, 2) . "\n" .
               "*Valid Until:* " . $estimate->expiry_date->format('d-M-Y') . "\n\n" .
               "Please find the attached PDF for details.";
    }

    /**
     * Get formatted payment receipt message
     */
    public function getPaymentMessage($payment): string
    {
        $companyName = CompanySetting::getSetting('company_name', $payment->company_id) ?? 'Our Company';
        $currency = CompanySetting::getSetting('currency', $payment->company_id) ?? '₹';
        
        return "✅ *Payment Receipt - {$companyName}*\n\n" .
               "*Payment No:* {$payment->payment_number}\n" .
               "*Amount:* {$currency} " . number_format($payment->base_amount, 2) . "\n" .
               "*Date:* " . $payment->payment_date->format('d-M-Y') . "\n\n" .
               "Thank you for your payment!";
    }

    /**
     * Get formatted report message
     */
    public function getReportMessage(string $reportType, string $fromDate, string $toDate): string
    {
        $companyName = CompanySetting::getSetting('company_name', $this->companyId) ?? 'Our Company';
        $reportTitle = ucwords(str_replace('_', ' ', $reportType));
        
        return "📊 *Report: {$reportTitle}*\n\n" .
               "*Company:* {$companyName}\n" .
               "*Period:* {$fromDate} to {$toDate}\n\n" .
               "Please find the attached report.";
    }
}
