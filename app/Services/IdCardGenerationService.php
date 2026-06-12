<?php
// app/Services/IdCardGenerationService.php

namespace App\Services;

use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Jobs\GenerateIdCardPdfJob;
use App\Models\Orphan;
use App\Models\Widow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IdCardGenerationService
{
    public function __construct(
        private QRCodeService $qrService
    ) {}

    /**
     * Generate ID card for a single beneficiary
     */
    public function generateCard(
        Widow|Orphan $beneficiary,
        ?IdCardTemplate $template = null,
        bool $queuePdf = true,
    ): IdCard {
        return DB::transaction(function () use ($beneficiary, $template, $queuePdf) {
            $type = $beneficiary instanceof Widow ? 'widow' : 'orphan';

            $template ??= IdCardTemplate::defaultForType($type);

            if ($template->type !== $type || ! $template->is_active) {
                throw new \RuntimeException("Selected template cannot be used for {$type} cards.");
            }

            // Generate unique card number
            $cardNumber = $this->generateCardNumber($type);

            $idCard = IdCard::create([
                'cardable_type' => get_class($beneficiary),
                'cardable_id' => $beneficiary->id,
                'template_id' => $template->id,
                'card_number' => $cardNumber,
                'qr_code_path' => '', // temporary
                'issued_at' => now(),
                'expires_at' => now()->addYears(2), // 2-year validity
                'status' => 'draft',
            ]);

            // Generate QR code
            $qrPath = $this->qrService->generateForCard($idCard);
            $idCard->update(['qr_code_path' => $qrPath]);

            if ($queuePdf) {
                GenerateIdCardPdfJob::dispatch($idCard);
            }

            return $idCard->fresh();
        });
    }

    /**
     * Generate cards for multiple beneficiaries
     */
    public function generateBulk(array $beneficiaryIds, string $type): array
    {
        $model = $type === 'widow' ? Widow::class : Orphan::class;
        $beneficiaries = $model::whereIn('id', $beneficiaryIds)
            ->where('is_eligible', true)
            ->get();

        $cards = [];
        foreach ($beneficiaries as $beneficiary) {
            // Skip if already has active card
            $existingCard = IdCard::where('cardable_type', get_class($beneficiary))
                ->where('cardable_id', $beneficiary->id)
                ->where('status', 'active')
                ->exists();

            if (!$existingCard) {
                $cards[] = $this->generateCard($beneficiary);
            }
        }

        return $cards;
    }

    public function generateCardNumber(string $type): string
    {
        $prefix = $type === 'widow' ? 'GOF-W' : 'GOF-O';
        $year = now()->year;
        $sequence = IdCard::where('card_number', 'like', "{$prefix}-{$year}-%")->count() + 1;

        do {
            $cardNumber = sprintf('%s-%d-%04d', $prefix, $year, $sequence++);
        } while (IdCard::where('card_number', $cardNumber)->exists());

        return $cardNumber;
    }

    /**
     * Generate cards by date range or ID range
     */
    public function generateByRange(
        string $type,
        ?string $startDate = null,
        ?string $endDate = null,
        ?string $startId = null,
        ?string $endId = null
    ): array {
        $model = $type === 'widow' ? Widow::class : Orphan::class;

        $query = $model::query()
            ->where('is_eligible', true)
            ->whereDoesntHave('idCards', function ($q) {
                $q->where('status', 'active');
            });

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        if ($startId && $endId) {
            $query->whereBetween('reg_no', [$startId, $endId]);
        }

        return $this->generateBulk($query->pluck('id')->toArray(), $type);
    }
}
