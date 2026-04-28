<?php
// app/Services/IdCardPDFService.php

namespace App\Services;

use App\Models\IdCard;
use App\Models\IdCardPrintBatch;
use App\Models\Widow;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class IdCardPDFService
{
    /**
     * Standard ID card dimensions (CR80): 85.60mm x 53.98mm
     * At 300 DPI: 1011 x 638 pixels
     */
    private const CARD_WIDTH_MM = 85.60;
    private const CARD_HEIGHT_MM = 53.98;
    private const DPI = 300;

    /**
     * Generate PDF for single card
     */
    public function generateSingle(IdCard $idCard): \Barryvdh\DomPDF\PDF
    {
        $data = $this->prepareCardData($idCard);

        // Convert MM to Points (1mm = 2.83465pt)
        $width = self::CARD_WIDTH_MM * 2.83465;
        $height = self::CARD_HEIGHT_MM * 2.83465;

        return Pdf::loadView('id-cards.template', $data)
            ->setPaper([0, 0, $width, $height], 'portrait') // portrait because width > height in our coords
            ->setOptions([
                'dpi' => self::DPI,
                'defaultFont' => 'Helvetica',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'chroot' => [public_path(), storage_path()],
            ]);
    }

    /**
     * Generate bulk PDF with multiple cards per page (10-up layout)
     */
    public function generateBulk(Collection $idCards, IdCardPrintBatch $batch): string
    {
        $cardsPerPage = 10; // 2 columns x 5 rows with cut marks
        $chunks = $idCards->chunk($cardsPerPage);

        $pages = [];
        foreach ($chunks as $chunk) {
            $pages[] = [
                'cards' => $chunk->map(fn($card) => $this->prepareCardData($card)),
                'showCutMarks' => true,
            ];
        }

        $pdf = Pdf::loadView('id-cards.bulk-template', [
            'pages' => $pages,
            'batch' => $batch,
            'foundationName' => 'Garko Orphans Foundation',
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'dpi' => self::DPI,
                'defaultFont' => 'Helvetica',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'chroot' => [public_path(), storage_path()],
            ]);

        $filename = 'print-batches/batch-' . $batch->id . '.pdf';
        Storage::disk('public')->put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Prepare data for card template
     */
    private function prepareCardData(IdCard $idCard): array
    {
        $beneficiary = $idCard->cardable;
        
        if (!$beneficiary) {
            throw new \Exception("Beneficiary not found for ID Card: {$idCard->card_number}");
        }

        $isWidow = $idCard->cardable_type === Widow::class;
        $gender = null;

        if (!$isWidow) {
            $gender = $beneficiary->gender instanceof \App\Enums\Gender 
                ? $beneficiary->gender->getLabel() 
                : (is_string($beneficiary->gender) ? $beneficiary->gender : 'N/A');
        } else {
            $gender = 'Female';
        }

        $logoPath = storage_path('app/public/logos/gof_logo.jpeg');
        if (!file_exists($logoPath)) {
            $logoPath = public_path('images/garko-logo.png');
        }

        return [
            'foundation_logo' => file_exists($logoPath) ? $logoPath : null,
            'foundation_name' => 'Garko Orphans Foundation',
            'card_type' => $isWidow ? 'WIDOW ID CARD' : 'ORPHAN ID CARD',
            'card_number' => $idCard->card_number,
            'photo_url' => ($beneficiary->picture_url && Storage::disk('public')->exists($beneficiary->picture_url))
                ? Storage::disk('public')->path($beneficiary->picture_url)
                : (file_exists(public_path('images/default-avatar.png'))
                    ? public_path('images/default-avatar.png')
                    : null),
            'full_name' => $beneficiary->full_name ?? 'N/A',
            'nin' => $this->maskNin($beneficiary->nin),
            'reg_no' => $beneficiary->reg_no ?? 'N/A',
            'date_of_birth' => (!$isWidow && isset($beneficiary->birth_date)) ? $beneficiary->birth_date?->format('M d, Y') : null,
            'age' => $isWidow ? null : ($beneficiary->age ?? null),
            'gender' => $gender,
            'address' => Str::limit($beneficiary->address ?? 'N/A', 60),
            'zone' => $beneficiary->zone?->name ?? 'N/A',
            'coordinator_name' => $beneficiary->zone?->coordinator_name ?? 'N/A',
            'coordinator_phone' => $beneficiary->zone?->coordinator_phone ?? 'N/A',
            'foundation_address' => 'Shop No.1, Garko Juma\'at Mosque, Garko Local Government, Kano',
            'issue_date' => $idCard->issued_at?->format('M d, Y') ?? now()->format('M d, Y'),
            'expiry_date' => $idCard->expires_at?->format('M d, Y'),
            'qr_code' => ($idCard->qr_code_path && Storage::disk('public')->exists($idCard->qr_code_path))
                ? Storage::disk('public')->path($idCard->qr_code_path)
                : null,
            'signature_text' => 'Authorized Signature',
            'background_color' => $isWidow ? '#FFF8F0' : '#F0F8FF',
            'accent_color' => $isWidow ? '#8B4513' : '#1E90FF',
        ];
    }

    /**
     * Mask NIN for privacy (show last 4 digits only)
     */
    private function maskNin(?string $nin): string
    {
        if (!$nin) return 'N/A';
        return str_repeat('•', strlen($nin) - 4) . substr($nin, -4);
    }
}
