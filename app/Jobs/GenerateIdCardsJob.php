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

    /**
     * @param  array<int, array{type: string, id: string}>|null  $beneficiaryDescriptors
     */
    public function __construct(
        private IdCardPrintBatch $batch,
        private ?array $beneficiaryDescriptors = null,
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
            $idCards = new \Illuminate\Database\Eloquent\Collection;
            $processed = 0;

            $beneficiaries = $this->resolveBeneficiaries();

            foreach ($beneficiaries as $beneficiary) {
                try {
                    $type = $beneficiary instanceof Widow ? 'widow' : 'orphan';
                    $template = ($this->batch->type === 'mixed' || ! $this->templateId)
                        ? IdCardTemplate::defaultForType($type)
                        : (IdCardTemplate::find($this->templateId) ?? IdCardTemplate::defaultForType($type));

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
                        'beneficiary_type' => get_class($beneficiary),
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

    private function resolveBeneficiaries(): Collection
    {
        if (! empty($this->beneficiaryDescriptors)) {
            $beneficiaries = collect();
            foreach ($this->beneficiaryDescriptors as $descriptor) {
                $type = $descriptor['type'] ?? 'widow';
                $id = $descriptor['id'] ?? null;
                if (! $id) {
                    continue;
                }

                $modelClass = $type === 'widow' ? Widow::class : Orphan::class;
                $model = $modelClass::find($id);
                if ($model) {
                    $beneficiaries->push($model);
                }
            }

            return $beneficiaries;
        }

        return static::resolveBeneficiariesFromBatch($this->batch);
    }

    public static function resolveBeneficiariesFromBatch(IdCardPrintBatch $batch): Collection
    {
        $type = $batch->type;
        $filters = $batch->filters ?? [];
        $range = $batch->range ?? [];

        $applyFilters = function ($query) use ($filters, $range) {
            $query->where('is_eligible', true);

            if ($filters['exclude_printed'] ?? true) {
                $query->whereDoesntHave('idCards', fn ($q) => $q->where('status', 'active'));
            }

            if (! empty($filters['zone_id'])) {
                $query->whereHas('deceased.zone', fn ($q) => $q->where('id', $filters['zone_id']));
            }

            if (! empty($filters['gender']) && $query->getModel() instanceof Orphan) {
                $query->where('gender', $filters['gender']);
            }

            if (! empty($range['start_date']) && ! empty($range['end_date'])) {
                $query->whereBetween('created_at', [$range['start_date'], $range['end_date']]);
            }

            if (! empty($range['start_reg_no']) && ! empty($range['end_reg_no'])) {
                $query->whereBetween('reg_no', [$range['start_reg_no'], $range['end_reg_no']]);
            }

            if (! empty($range['specific_ids'])) {
                $query->whereIn('id', $range['specific_ids']);
            }

            return $query;
        };

        if ($type === 'mixed') {
            return $applyFilters(Widow::query())
                ->get()
                ->merge($applyFilters(Orphan::query())->get());
        }

        $modelClass = $type === 'widow' ? Widow::class : Orphan::class;

        return $applyFilters($modelClass::query())->get();
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
