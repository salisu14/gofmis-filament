<?php

use App\Http\Controllers\IdCardController;
use App\Http\Controllers\IdCardDownloadController;
use App\Http\Controllers\OrphanReportController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\WidowLoanRepaymentController;
use App\Http\Controllers\WidowLoanWriteOffController;
use Illuminate\Support\Facades\Route;

Route::get('/', PortalController::class)->name('home');

Route::get('/id-cards/{idCard}/download', IdCardDownloadController::class)
    ->name('id-cards.download')
    ->middleware('auth');

Route::get('/id-cards/{card}/preview', [IdCardController::class, 'preview'])
    ->name('id-cards.preview')
    ->middleware('auth');

// Local debug route: render the card HTML directly to isolate preview issues.
if (app()->environment('local')) {
    Route::get('/dev/id-cards/{card}/preview-debug', function (\App\Models\IdCard $card) {
        $beneficiary = $card->cardable;
        $isWidow = $card->cardable_type === \App\Models\Widow::class;
        $company = app(\App\Services\Company\CompanyInformationService::class)->reportHeader();

        $photo = null;
        if ($beneficiary) {
            $photo = ($beneficiary->picture_url && \Illuminate\Support\Facades\Storage::disk('public')->exists($beneficiary->picture_url))
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($beneficiary->picture_url)
                : (file_exists(public_path('images/default-avatar.png')) ? asset('images/default-avatar.png') : null);
        }

        return view('id-cards.card-content', [
            'foundation_logo' => $company['logo_url'] ?? null,
            'foundation_name' => $company['name'],
            'foundation_address' => $company['address'],
            'card_type' => $isWidow ? 'WIDOW ID CARD' : 'ORPHAN ID CARD',
            'card_number' => $card->card_number,
            'photo_url' => $photo,
            'full_name' => $beneficiary->full_name ?? 'N/A',
            'nin' => $beneficiary->nin ?? 'N/A',
            'reg_no' => $beneficiary->reg_no ?? 'N/A',
            'gender' => $beneficiary->gender ?? 'N/A',
            'zone' => $beneficiary->zone?->name ?? 'N/A',
            'coordinator_name' => $beneficiary->zone?->coordinator_name ?? 'N/A',
            'coordinator_phone' => $beneficiary->zone?->coordinator_phone ?? 'N/A',
            'issue_date' => $card->issued_at?->format('M d, Y') ?? now()->format('M d, Y'),
            'expiry_date' => $card->expires_at?->format('M d, Y') ?? null,
            'background_color' => $isWidow ? '#FFF8F0' : '#F0F8FF',
            'accent_color' => $isWidow ? '#8B4513' : '#1E90FF',
            'qr_code' => ($card->qr_code_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($card->qr_code_path))
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($card->qr_code_path)
                : null,
        ]);
    })->middleware('auth');
}
Route::get('/id-card-print-batches/{record}/download', \App\Http\Controllers\IdCardPrintBatchDownloadController::class)
    ->name('id-card-print-batches.download')
    ->middleware('auth');

Route::get('/verify-id-card/{card}', [IdCardController::class, 'verify'])
    ->name('id-cards.verify')
    ->middleware('signed');

if (app()->environment('local')) {
    Route::get('/debug-routes', function () {
        $routes = collect(\Illuminate\Support\Facades\Route::getRoutes())
            ->filter(fn ($r) => str_contains($r->getName() ?? '', 'healthcare'))
            ->map(fn ($r) => $r->getName())
            ->values();

        return response()->json($routes);
    })->middleware('auth');
}

// Orphan Dossier Report Route
Route::get('/orphans/{orphan}/report', [OrphanReportController::class, 'download'])
    ->name('orphans.report.download')
    ->middleware('auth');

// Loan Repayment Receipt Download Route
Route::get('/repayments/{repayment}/receipt', [WidowLoanRepaymentController::class, 'downloadReceipt'])
    ->name('repayments.receipt.download')
    ->middleware('auth');

Route::get('/repayments/{repayment}/thermal-receipt', [WidowLoanRepaymentController::class, 'downloadThermalReceipt'])
    ->name('repayments.thermal-receipt.download')
    ->middleware('auth');

Route::get('/loans/{loan}/statement', [WidowLoanRepaymentController::class, 'downloadStatement'])
    ->name('loans.statement.download')
    ->middleware('auth');

// TRUE 58mm thermal WRL WEEKLY repayment report (aggregate reconciliation).
// Query params: week=YYYY-MM-DD (normalized to the containing ISO Monday-Sunday
// week), optional zone=<uuid> (admin/super_admin only; coordinators are always
// scoped to their own zone).
Route::get('/wrl/reports/weekly', [WidowLoanRepaymentController::class, 'downloadWeeklyReport'])
    ->name('wrl.weekly.download')
    ->middleware('auth');

Route::get('/loans/write-off-documents/{writeOff}', [WidowLoanWriteOffController::class, 'downloadDocument'])
    ->name('loans.write-off-document.download')
    ->middleware('auth');

// Healthcare Prescription Document Routes
Route::get('/prescriptions/{prescription}/preview', [\App\Http\Controllers\PrescriptionDocumentController::class, 'preview'])
    ->name('prescriptions.preview')
    ->middleware('auth');

Route::get('/prescriptions/{prescription}/download', [\App\Http\Controllers\PrescriptionDocumentController::class, 'download'])
    ->name('prescriptions.download')
    ->middleware('auth');

// Project Print Route
Route::get('/projects/print', [\App\Http\Controllers\ProjectReportController::class, 'exportPdf'])
    ->name('reports.project-report.pdf')
    ->middleware('auth');

Route::get('/prescriptions/{prescription}/referral/preview', [\App\Http\Controllers\PrescriptionDocumentController::class, 'referralPreview'])
    ->name('prescriptions.referral.preview')
    ->middleware('auth');

Route::get('/prescriptions/{prescription}/referral/download', [\App\Http\Controllers\PrescriptionDocumentController::class, 'referralDownload'])
    ->name('prescriptions.referral.download')
    ->middleware('auth');

// Healthcare Period Report PDF Export Route
Route::get('/admin/reports/prescription-report/pdf', [\App\Http\Controllers\PrescriptionReportController::class, 'exportPdf'])
    ->name('reports.prescription-report.pdf')
    ->middleware('auth');

// Consolidated Financial Report PDF Export Route
Route::get('/admin/consolidated-financial-report/pdf', [\App\Http\Controllers\ConsolidatedFinancialReportController::class, 'exportPdf'])
    ->name('reports.consolidated-financial-report.pdf')
    ->middleware('auth');

Route::middleware(['auth'])->group(function () {
    Route::get('/mfa/challenge', \App\Livewire\Mfa\MfaChallenge::class)->name('mfa.challenge');
    Route::get('/mfa/enroll', \App\Livewire\Mfa\MfaEnroll::class)->name('mfa.enroll');
    Route::get('/mfa/recovery', \App\Livewire\Mfa\MfaRecovery::class)->name('mfa.recovery');
    Route::get('/mfa/settings', \App\Livewire\Mfa\MfaSettings::class)->name('mfa.settings');
    Route::post('/mfa/logout', function () {
        \Illuminate\Support\Facades\Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->to('/admin/login');
    })->name('mfa.logout');
});
