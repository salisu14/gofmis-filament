<?php
// app/Jobs/GenerateIdCardsJob.php

namespace App\Jobs;

use App\Models\IdCardPrintBatch;
use App\Models\IdCardTemplate;
use App\Models\Orphan;
use App\Models\Widow;
use App\Services\IdCardGenerationService;
use App\Services\IdCardPDFService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class GenerateIdCardsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 3;

    public function __construct(
        private IdCardPrintBatch $batch,
        private Collection $beneficiaries,
        private ?string $templateId = null,
    ) {}

    public function handle(
        IdCardGenerationService $generationService,
        IdCardPDFService $pdfService
    ): void {
        $this->batch->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
//            $idCards = collect();
            $idCards = new \Illuminate\Database\Eloquent\Collection();
            $processed = 0;
            $template = $this->templateId ? IdCardTemplate::find($this->templateId) : null;

            foreach ($this->beneficiaries as $beneficiary) {
                try {
                    $card = $this->reusableCardFor($beneficiary, $template)
                        ?? $generationService->generateCard($beneficiary, $template, queuePdf: false);

                    $idCards->push($card);
                    $processed++;

                    // Update progress every 10 cards
                    if ($processed % 10 === 0) {
                        $this->batch->update(['processed_count' => $processed]);
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to generate card for beneficiary', [
                        'beneficiary_id' => $beneficiary->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Generate bulk PDF
            if ($idCards->isEmpty()) {
                throw new \RuntimeException('No ID cards could be generated for this batch.');
            }

            $idCards->loadMissing(['cardable.deceased.zone.coordinator', 'template']);
            $pdfPath = $pdfService->generateBulk($idCards, $this->batch);
            $idCards->each->markAsPrinted();

            $this->batch->update([
                'status' => 'completed',
                'completed_at' => now(),
                'pdf_path' => $pdfPath,
                'processed_count' => $processed,
            ]);

        } catch (\Exception $e) {
            $this->batch->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            Log::error('Batch processing failed', [
                'batch_id' => $this->batch->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function reusableCardFor(Widow|Orphan $beneficiary, ?IdCardTemplate $template)
    {
        return $beneficiary->idCards()
            ->when($template, fn ($query) => $query->where('template_id', $template->id))
            ->whereIn('status', ['draft', 'active'])
            ->latest('created_at')
            ->first();
    }
}
