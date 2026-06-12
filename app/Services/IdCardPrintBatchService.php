<?php

namespace App\Services;

use App\Jobs\GenerateIdCardsJob;
use App\Models\IdCard;
use App\Models\IdCardPrintBatch;
use App\Models\Orphan;
use App\Models\Widow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IdCardPrintBatchService
{
    public function process(IdCardPrintBatch $batch): IdCardPrintBatch
    {
        if (($batch->filters['source'] ?? null) === 'selected_id_cards') {
            return $this->processSelectedCardsBatch($batch);
        }

        $beneficiaries = $this->beneficiariesFor($batch);

        if ($beneficiaries->isEmpty()) {
            $batch->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            throw new \RuntimeException('No eligible beneficiaries were found for this print batch.');
        }

        GenerateIdCardsJob::dispatchSync(
            $batch,
            $beneficiaries,
            $batch->filters['template_id'] ?? null,
        );

        return $batch->fresh();
    }

    public function beneficiariesFor(IdCardPrintBatch $batch): Collection
    {
        if ($batch->type === 'mixed') {
            return $this->applyFilters(Widow::query(), $batch)
                ->get()
                ->merge($this->applyFilters(Orphan::query(), $batch)->get());
        }

        $model = $batch->type === 'widow' ? Widow::class : Orphan::class;

        return $this->applyFilters($model::query(), $batch)->get();
    }

    private function processSelectedCardsBatch(IdCardPrintBatch $batch): IdCardPrintBatch
    {
        $cards = IdCard::query()
            ->with(['cardable.deceased.zone.coordinator', 'template'])
            ->whereIn('id', $batch->filters['card_ids'] ?? [])
            ->get();

        if ($cards->isEmpty()) {
            $batch->update([
                'status' => 'failed',
                'completed_at' => now(),
            ]);

            throw new \RuntimeException('No ID cards were found for this selected-card batch.');
        }

        $batch->update([
            'status' => 'processing',
            'started_at' => now(),
            'processed_count' => 0,
        ]);

        $pdfPath = app(IdCardPDFService::class)->generateBulk($cards, $batch);
        $cards->each->markAsPrinted();

        $batch->update([
            'pdf_path' => $pdfPath,
            'processed_count' => $cards->count(),
            'total_count' => $cards->count(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $batch->fresh();
    }

    private function applyFilters(Builder $query, IdCardPrintBatch $batch): Builder
    {
        $filters = $batch->filters ?? [];
        $range = $batch->range ?? [];

        $query->where('is_eligible', true);

        $excludePrinted = array_key_exists('exclude_printed', $filters)
            ? (bool) $filters['exclude_printed']
            : false;

        if ($excludePrinted) {
            $query->whereDoesntHave('idCards', fn ($idCards) => $idCards->where('status', 'active'));
        }

        if (! empty($filters['zone_id'])) {
            $query->whereHas('deceased.zone', fn ($zone) => $zone->whereKey($filters['zone_id']));
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

        if (! empty($range['from']) && ! empty($range['to'])) {
            $query->whereBetween('reg_no', [$range['from'], $range['to']]);
        }

        if (! empty($range['specific_ids'])) {
            $query->whereIn('id', $range['specific_ids']);
        }

        return $query;
    }
}
