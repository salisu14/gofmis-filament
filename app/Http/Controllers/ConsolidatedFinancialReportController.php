<?php

namespace App\Http\Controllers;

use App\Services\Company\CompanyInformationService;
use App\Services\ConsolidatedFinancialReportService;
use App\Services\Security\DemoReadOnlyGuard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ConsolidatedFinancialReportController extends Controller
{
    public function exportPdf(Request $request, ConsolidatedFinancialReportService $reportService, CompanyInformationService $companyService)
    {
        DemoReadOnlyGuard::ensureCanExportSensitiveData();

        $user = auth()->user();

        if (! $user) {
            abort(401);
        }

        if (method_exists($user, 'isCoordinator') && $user->isCoordinator()) {
            abort(403, 'Coordinators cannot access financial reports.');
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('coordinator')) {
            abort(403, 'Coordinators cannot access financial reports.');
        }

        $canExport = $user->isAdmin()
            || $user->isSuperAdmin()
            || $user->can('finance.consolidated_report.export')
            || $user->can('export_reports');

        if (! $canExport) {
            abort(403, 'Unauthorized to export consolidated financial report.');
        }

        $filters = [
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'classification' => $request->input('classification'),
            'type' => $request->input('type'),
            'bank_account_id' => $request->input('bank_account_id'),
            'search' => $request->input('search'),
            'amount_min' => $request->input('amount_min'),
            'amount_max' => $request->input('amount_max'),
        ];

        $mode = $request->input('mode', 'all');

        $kpis = $reportService->getKpis($filters, $mode);
        $transactions = $reportService->getTransactionsQuery($filters, $mode)->get();
        $company = $companyService->reportHeader();

        $pdf = Pdf::loadView('pdf.reports.consolidated-financial-report', compact(
            'filters',
            'mode',
            'kpis',
            'transactions',
            'company'
        ))->setPaper('a4', 'landscape');

        $filename = 'consolidated-financial-report-'.now()->format('Y-m-d-His').'.pdf';

        return $pdf->download($filename);
    }
}
