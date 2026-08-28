<?php

namespace App\Http\Controllers;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Services\Company\CompanyInformationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ProjectReportController extends Controller
{
    public function exportPdf(Request $request, CompanyInformationService $companyService)
    {
        if (! auth()->user()->isAdmin() && ! auth()->user()->isSuperAdmin() && ! auth()->user()->can('view_projects')) {
            abort(403);
        }

        $startDate = $request->input('start_date', now()->startOfYear()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $status = $request->input('status');

        $query = Project::query()
            ->with(['zone', 'coordinator'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $projects = $query->get();

        $metrics = [
            'total_projects' => $projects->count(),
            'completed_projects' => $projects->where('status', ProjectStatus::COMPLETED)->count(),
            'active_projects' => $projects->where('status', ProjectStatus::IN_PROGRESS)->count(),
            'total_budget' => (float) $projects->sum('budget_allocated'),
            'total_spent' => (float) $projects->sum('budget_spent'),
        ];

        $company = $companyService->reportHeader();
        $action = $request->input('action', 'download');

        $pdf = Pdf::loadView('pdf.reports.project-report', compact(
            'projects',
            'metrics',
            'startDate',
            'endDate',
            'status',
            'company'
        ))->setPaper('a4', 'landscape');

        $filename = 'project-report-'.now()->format('Y-m-d').'.pdf';

        if ($action === 'preview') {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }
}
