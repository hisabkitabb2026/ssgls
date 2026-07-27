<?php

namespace App\Http\Controllers\Company\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Estimate;
use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Report\ProfitLossCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Silber\Bouncer\BouncerFacade;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProfitLossCalculationService $profitLossService,
    ) {}

    /**
     * Handle the incoming request.
     *
     * @return JsonResponse
     */
    public function __invoke(Request $request)
    {
        $company = Company::find($request->header('company'));

        $this->authorize('view dashboard', $company);

        // Use Profit & Loss calculation based on LR Receipts (transport module)
        // This matches the Profit Loss Report calculation
        $fiscalYear = CompanySetting::getSetting('fiscal_year', $request->header('company'));
        $startDate = Carbon::now();
        $start = Carbon::now();
        $end = Carbon::now();
        $terms = explode('-', $fiscalYear);
        $companyStartMonth = intval($terms[0]);

        if ($companyStartMonth <= $start->month) {
            $startDate->month($companyStartMonth)->startOfMonth();
            $start->month($companyStartMonth)->startOfMonth();
            $end->month($companyStartMonth)->endOfMonth();
        } else {
            $startDate->subYear()->month($companyStartMonth)->startOfMonth();
            $start->subYear()->month($companyStartMonth)->startOfMonth();
            $end->subYear()->month($companyStartMonth)->endOfMonth();
        }

        if ($request->has('previous_year')) {
            $startDate->subYear()->startOfMonth();
            $start->subYear()->startOfMonth();
            $end->subYear()->endOfMonth();
        }

        // Calculate monthly data for the chart
        $invoice_totals = []; // Sales (Invoice totals)
        $receipt_totals = []; // LR Amount (amount_credit - amount_debit from LR Receipts)
        $expense_totals = []; // Expenses (from Expenses module)
        $net_income_totals = []; // Net Income (LR Amount - Expenses)
        $months = [];
        $i = 0;
        $monthCounter = 0;

        while ($monthCounter < 12) {
            // Sales: Sum of invoice totals for the month
            $invoice_totals[] = Invoice::whereBetween(
                'invoice_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )
                ->whereCompany()
                ->sum('base_total');

            // Calculate LR Amount from LR Receipts (amount_credit - amount_debit)
            $lrReceipts = Invoice::where('template_name', 'lr_receipt')
                ->where('company_id', $request->header('company'))
                ->whereBetween('invoice_date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                ->get();

            // LR Amount = Sum of (amount_credit - amount_debit) from LR Receipts
            $lrAmount = $lrReceipts->sum('amount_credit') - $lrReceipts->sum('amount_debit');
            $receipt_totals[] = $lrAmount;

            // Expenses: Sum from Expenses module
            $expenses = Expense::whereBetween(
                'expense_date',
                [$start->format('Y-m-d'), $end->format('Y-m-d')]
            )
                ->whereCompany()
                ->sum('base_amount');
            $expense_totals[] = $expenses;

            // Net Income = LR Amount - Expenses
            $net_income_totals[] = $lrAmount - $expenses;
            
            $months[] = $start->translatedFormat('M');
            
            $i++;
            $monthCounter++;
            $end->startOfMonth();
            $start->addMonth()->startOfMonth();
            $end->addMonth()->endOfMonth();
        }

        $start->subMonth()->endOfMonth();

        // Calculate yearly totals
        // Sales: Sum of all invoice totals
        $total_sales = Invoice::whereBetween(
            'invoice_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->sum('base_total');

        // LR Amount: Sum of (amount_credit - amount_debit) from LR Receipts
        $totalLrReceipts = Invoice::where('template_name', 'lr_receipt')
            ->where('company_id', $request->header('company'))
            ->whereBetween('invoice_date', [$startDate->format('Y-m-d'), $start->format('Y-m-d')])
            ->get();

        $total_receipts = $totalLrReceipts->sum('amount_credit') - $totalLrReceipts->sum('amount_debit');

        // Expenses: Sum from Expenses module
        $total_expenses = Expense::whereBetween(
            'expense_date',
            [$startDate->format('Y-m-d'), $start->format('Y-m-d')]
        )
            ->whereCompany()
            ->sum('base_amount');

        // Net Income = LR Amount - Expenses
        $total_net_income = $total_receipts - $total_expenses;

        $chart_data = [
            'months' => $months,
            'invoice_totals' => $invoice_totals, // Sales
            'expense_totals' => $expense_totals, // Expenses
            'receipt_totals' => $receipt_totals, // LR Amount
            'net_income_totals' => $net_income_totals, // Net Income
        ];

        $total_customer_count = Customer::whereCompany()->count();
        $total_invoice_count = Invoice::whereCompany()
            ->whereNotIn('template_name', ['lr_receipt', 'lorry_receipt'])
            ->count();
        $total_estimate_count = Estimate::whereCompany()->count();
        
        // Count LR Receipts and Lorry Receipts (transport module)
        $total_lr_receipt_count = Invoice::whereCompany()
            ->where('template_name', 'lr_receipt')
            ->count();
        $total_lorry_receipt_count = Invoice::whereCompany()
            ->where('template_name', 'lorry_receipt')
            ->count();
        
        $total_amount_due = Invoice::whereCompany()
            ->sum('base_due_amount');

        $recent_due_invoices = Invoice::with('customer')
            ->whereCompany()
            ->where('base_due_amount', '>', 0)
            ->take(5)
            ->latest()
            ->get();
        $recent_estimates = Estimate::with('customer')->whereCompany()->take(5)->latest()->get();

        return response()->json([
            'total_amount_due' => $total_amount_due,
            'total_customer_count' => $total_customer_count,
            'total_invoice_count' => $total_invoice_count,
            'total_estimate_count' => $total_estimate_count,
            'total_lr_receipt_count' => $total_lr_receipt_count,
            'total_lorry_receipt_count' => $total_lorry_receipt_count,

            'recent_due_invoices' => BouncerFacade::can('view-invoice', Invoice::class) ? $recent_due_invoices : [],
            'recent_estimates' => BouncerFacade::can('view-estimate', Estimate::class) ? $recent_estimates : [],

            'chart_data' => $chart_data,

            'total_sales' => $total_sales,
            'total_receipts' => $total_receipts,
            'total_expenses' => $total_expenses,
            'total_net_income' => $total_net_income,
        ]);
    }
}
