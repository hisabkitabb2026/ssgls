<?php

namespace App\Http\Controllers\Company\Report;

use App\Facades\Pdf;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Report\ProfitLossCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class ProfitLossReportController extends Controller
{
    public function __construct(
        private readonly ProfitLossCalculationService $profitLossService,
    ) {}

    /**
     * Handle the incoming request.
     *
     * @param  string  $hash
     * @return JsonResponse
     */
    public function __invoke(Request $request, $hash)
    {
        $company = Company::where('unique_hash', $hash)->first();

        $this->authorize('view report', $company);

        $locale = CompanySetting::getSetting('language', $company->id);

        App::setLocale($locale);

        $dateFormat = CompanySetting::getSetting('carbon_date_format', $company->id);

        // Fetch all LR Receipts in date range, optionally filtered by customer name/id
        $lrReceipts = Invoice::with(['customer', 'consigneeCustomer', 'ownerCustomer', 'driverCustomer', 'brokerCustomer'])
            ->where('company_id', $company->id)
            ->where('template_name', 'lr_receipt')
            ->when($request->from_date, fn ($query, $date) => $query->where('invoice_date', '>=', $date))
            ->when($request->to_date, fn ($query, $date) => $query->where('invoice_date', '<=', $date))
            ->when($request->customer_id, function ($query) use ($request, $company) {
                $customer = Customer::find($request->customer_id);
                if ($customer) {
                    $normalizedName = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $customer->name));
                    $allIds = Customer::where('customers.company_id', $company->id)
                        ->get(['id', 'name'])
                        ->filter(function ($c) use ($normalizedName) {
                            return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $c->name)) === $normalizedName;
                        })
                        ->pluck('id')
                        ->toArray();

                    $query->where(function ($q) use ($allIds) {
                        $q->whereIn('customer_id', $allIds)
                          ->orWhereIn('consignee_customer_id', $allIds);
                    });
                }
            })
            ->when($request->customer_name, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->whereHas('customer', function ($sub) use ($request) {
                        $sub->where('name', 'LIKE', '%'.$request->customer_name.'%');
                    })->orWhereHas('consigneeCustomer', function ($sub) use ($request) {
                        $sub->where('name', 'LIKE', '%'.$request->customer_name.'%');
                    });
                });
            })
            ->get();

        // Group LR receipts by customer based on GST Tax Payable By field
        $customerProfitLoss = [];
        $grandTotalNetProfit = 0;

        foreach ($lrReceipts as $lrReceipt) {
            // Get GST Tax Payable By custom field value
            $gstTaxPayableBy = $lrReceipt->getCustomFieldValueBySlug('gst-tax-payable-by');
            
            // Determine which customer to attribute this LR to
            $payingCustomerType = strtoupper($gstTaxPayableBy ?? '') === 'CONSIGNEE' ? 'CONSIGNEE' : 'CONSIGNOR';
            
            // Get the appropriate customer ID
            $customerId = null;
            if ($payingCustomerType === 'CONSIGNEE' && $lrReceipt->consignee_customer_id) {
                $customerId = $lrReceipt->consignee_customer_id;
            } else {
                $customerId = $lrReceipt->customer_id;
            }

            // Skip if no customer
            if (!$customerId) {
                continue;
            }

            // Calculate income and expense for this LR (both amount_credit and amount_debit are in cents)
            $amountCredit = (float) $lrReceipt->amount_credit;
            $amountDebit = (float) $lrReceipt->amount_debit;
            $netProfit = $amountCredit - $amountDebit;

            // Get customer name
            $customerName = Customer::where('id', $customerId)->value('name') ?? 'Unknown';

            // Initialize customer entry if not exists
            if (!isset($customerProfitLoss[$customerId])) {
                $customerProfitLoss[$customerId] = [
                    'id' => $customerId,
                    'name' => $customerName,
                    'lrReceipts' => [],
                    'totalIncome' => 0,
                    'totalNetProfit' => 0,
                ];
            }

            // Add LR receipt to customer's list
            $customerProfitLoss[$customerId]['lrReceipts'][] = [
                'lr_no' => $lrReceipt->invoice_number,
                'lr_date' => $lrReceipt->invoice_date ? Carbon::parse($lrReceipt->invoice_date)->format('Y-m-d') : '',
                'amount_credit' => $amountCredit,
                'amount_credit_date' => $lrReceipt->amount_credit_date,
                'amount_debit' => $amountDebit,
                'amount_debit_date' => $lrReceipt->amount_debit_date,
                'income' => $amountCredit,
                'net_profit' => $netProfit,
                'office_invoice_no' => $lrReceipt->office_invoice_number,
                'challan_no' => $lrReceipt->challan_number,
            ];

            // Update customer totals
            $customerProfitLoss[$customerId]['totalIncome'] += $amountCredit;
            $customerProfitLoss[$customerId]['totalNetProfit'] += $netProfit;

            // Update grand total (Net Profit = Income - Expenses)
            $grandTotalNetProfit += $netProfit;
        }

        // Convert to collection for the view
        $customersData = collect($customerProfitLoss)->values();

        $from_date = Carbon::createFromFormat('Y-m-d', $request->from_date)->translatedFormat($dateFormat);
        $to_date = Carbon::createFromFormat('Y-m-d', $request->to_date)->translatedFormat($dateFormat);
        $currency = Currency::findOrFail(CompanySetting::getSetting('currency', $company->id));

        // Calculate total gross income for the view (amount_credit is already in cents)
        $totalIncome = 0;
        foreach ($lrReceipts as $lrReceipt) {
            $totalIncome += (float) $lrReceipt->amount_credit;
        }

        view()->share([
            'company' => $company,
            'customersData' => $customersData,
            'grandTotalNetProfit' => $grandTotalNetProfit,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'currency' => $currency,
            'income' => $totalIncome,
            'totalExpense' => $totalIncome - $grandTotalNetProfit,
            'netProfit' => $grandTotalNetProfit,
            'lrReceipts' => $lrReceipts,
        ]);

        $pdf = Pdf::loadView('app.pdf.reports.profit-loss');

        if ($request->has('preview')) {
            return view('app.pdf.reports.profit-loss');
        }

        if ($request->has('download')) {
            return $pdf->download();
        }

        return $pdf->stream();
    }
}
