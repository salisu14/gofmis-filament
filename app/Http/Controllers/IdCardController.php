<?php
// app/Http/Controllers/IdCardController.php

namespace App\Http\Controllers;

use App\Jobs\GenerateIdCardsJob;
use App\Models\IdCard;
use App\Models\IdCardPrintBatch;
use App\Models\IdCardTemplate;
use App\Models\Orphan;
use App\Models\Widow;
use App\Services\IdCardGenerationService;
use App\Services\IdCardPDFService;
use App\Services\QRCodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class IdCardController extends Controller
{
    public function __construct(
        private IdCardGenerationService $generationService,
        private IdCardPDFService $pdfService,
        private QRCodeService $qrService
    ) {}

    /**
     * Generate card for single beneficiary
     */
    public function generate(Request $request, string $type, string $id)
    {
        $request->validate([
            'template_id' => 'nullable|exists:id_card_templates,id',
        ]);

        $model = $type === 'widow' ? Widow::class : Orphan::class;
        $beneficiary = $model::findOrFail($id);

        // Check eligibility
        if (!$beneficiary->is_eligible) {
            return response()->json([
                'message' => 'Beneficiary is not eligible for ID card generation'
            ], 422);
        }

        $template = $request->template_id
            ? IdCardTemplate::find($request->template_id)
            : null;

        $card = $this->generationService->generateCard($beneficiary, $template);

        return response()->json([
            'message' => 'ID card generated successfully',
            'data' => $card->load('cardable'),
        ]);
    }

    /**
     * Preview card before printing
     */
    public function preview(IdCard $card)
    {
        $pdf = $this->pdfService->generateSingle($card);

        $content = $pdf->output();

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview-' . $card->card_number . '.pdf"',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Download single card PDF
     */
    public function download(IdCard $card)
    {
        $card->markAsPrinted();

        $pdf = $this->pdfService->generateSingle($card);

        return $pdf->download('id-card-' . $card->card_number . '.pdf');
    }

    /**
     * Create print batch with filters and ranges
     */
    public function createBatch(Request $request)
    {
        $validated = $request->validate([
            'batch_name' => 'required|string|max:255',
            'type' => 'required|in:widow,orphan,mixed',
            'filters' => 'nullable|array',
            'filters.zone_id' => 'nullable|exists:zones,id',
            'filters.is_eligible' => 'nullable|boolean',
            'filters.gender' => 'nullable|in:male,female',
            'range' => 'nullable|array',
            'range.start_reg_no' => 'nullable|string',
            'range.end_reg_no' => 'nullable|string',
            'range.start_date' => 'nullable|date',
            'range.end_date' => 'nullable|date',
            'range.specific_ids' => 'nullable|array',
            'range.specific_ids.*' => 'uuid',
        ]);

        return DB::transaction(function () use ($validated) {
            // Build query based on filters
            $query = $validated['type'] === 'mixed'
                ? null
                : ($validated['type'] === 'widow' ? Widow::query() : Orphan::query());

            $cards = collect();

            if ($validated['type'] === 'mixed') {
                $widowCards = $this->getCardsByFilters(Widow::query(), $validated['filters'] ?? [], $validated['range'] ?? []);
                $orphanCards = $this->getCardsByFilters(Orphan::query(), $validated['filters'] ?? [], $validated['range'] ?? []);
                $cards = $widowCards->merge($orphanCards);
            } else {
                $cards = $this->getCardsByFilters($query, $validated['filters'] ?? [], $validated['range'] ?? []);
            }

            if ($cards->isEmpty()) {
                return response()->json([
                    'message' => 'No eligible beneficiaries found for the selected criteria'
                ], 422);
            }

            $batch = IdCardPrintBatch::create([
                'batch_name' => $validated['batch_name'],
                'type' => $validated['type'],
                'filters' => $validated['filters'] ?? [],
                'range' => $validated['range'] ?? null,
                'total_count' => $cards->count(),
                'created_by' => auth()->id(),
            ]);

            // Dispatch job for async processing
            GenerateIdCardsJob::dispatch($batch, $cards);

            return response()->json([
                'message' => 'Print batch created and processing started',
                'data' => $batch,
            ]);
        });
    }

    /**
     * Get batch status
     */
    public function batchStatus(IdCardPrintBatch $batch)
    {
        return response()->json([
            'data' => $batch,
            'progress_percentage' => $batch->progressPercentage(),
            'download_url' => $batch->status === 'completed'
                ? Storage::disk('public')->url($batch->pdf_path)
                : null,
        ]);
    }

    /**
     * Download batch PDF
     */
    public function downloadBatch(IdCardPrintBatch $batch)
    {
        if ($batch->status !== 'completed') {
            return response()->json([
                'message' => 'Batch is still processing'
            ], 422);
        }

        return response()->download(
            Storage::disk('public')->path($batch->pdf_path),
            $batch->batch_name . '.pdf'
        );
    }

    /**
     * Verify card via QR code scan (public endpoint)
     */
    public function verify(Request $request, IdCard $card)
    {
        // Validate signed URL
        if (!$request->hasValidSignature()) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired QR code'
            ], 403);
        }

        $result = $this->qrService->verify($card->id);

        return response()->json($result);
    }

    /**
     * Revoke a card
     */
    public function revoke(Request $request, IdCard $card)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $card->revoke($request->reason);

        return response()->json([
            'message' => 'ID card revoked successfully',
            'data' => $card,
        ]);
    }

    /**
     * Helper: Get cards by filters and ranges
     */
    private function getCardsByFilters($query, array $filters, array $range)
    {
        // Apply filters
        if (!empty($filters['zone_id'])) {
            $query->whereHas('deceased.zone', fn($q) => $q->where('id', $filters['zone_id']));
        }

        if (isset($filters['is_eligible'])) {
            $query->where('is_eligible', $filters['is_eligible']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        // Apply range filters
        if (!empty($range['start_date']) && !empty($range['end_date'])) {
            $query->whereBetween('created_at', [$range['start_date'], $range['end_date']]);
        }

        if (!empty($range['start_reg_no']) && !empty($range['end_reg_no'])) {
            $query->whereBetween('reg_no', [$range['start_reg_no'], $range['end_reg_no']]);
        }

        if (!empty($range['specific_ids'])) {
            $query->whereIn('id', $range['specific_ids']);
        }

        // Only get those without active cards
        return $query->where('is_eligible', true)
            ->whereDoesntHave('idCards', fn($q) => $q->where('status', 'active'))
            ->get();
    }
}
