<?php

use App\Http\Controllers\IdCardController;
use App\Http\Controllers\IdCardDownloadController;
use App\Http\Controllers\WidowLoanRepaymentController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
            ->filter(fn($r) => str_contains($r->getName() ?? '', 'healthcare'))
            ->map(fn($r) => $r->getName())
            ->values();

        return response()->json($routes);
    })->middleware('auth');
}


// Loan Repayment Receipt Download Route
Route::get('/repayments/{repayment}/receipt', [WidowLoanRepaymentController::class, 'downloadReceipt'])
    ->name('repayments.receipt.download')
    ->middleware('auth');

Route::get('/loans/{loan}/statement', [WidowLoanRepaymentController::class, 'downloadStatement'])
    ->name('loans.statement.download')
    ->middleware('auth');
