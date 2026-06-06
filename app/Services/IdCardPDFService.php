<?php

// app/Services/IdCardPDFService.php

namespace App\Services;

use App\Models\IdCard;
use App\Models\IdCardPrintBatch;
use App\Models\Widow;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
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

        $pdf = Pdf::loadView('id-cards.template', $data)
            ->setPaper([0, 0, $width, $height], 'portrait')
            ->setOptions([
                'dpi' => self::DPI,
                'defaultFont' => 'Helvetica',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'chroot' => [public_path(), storage_path()],
            ]);

        $this->trimTrailingBlankPages($pdf);

        return $pdf;
    }

    public function prepareCardDataForBrowser(IdCard $idCard): array
    {
        return $this->prepareCardData($idCard, forBrowser: true);
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
                'cards' => $chunk->map(fn ($card) => $this->prepareCardData($card))->values()->all(),
                'showCutMarks' => true,
            ];
        }

        $pdf = Pdf::loadView('id-cards.bulk-template', [
            'pages' => $pages,
            'batch' => $batch,
            'foundationName' => 'Garko Orphans Foundation',
            'accentColor' => '#1E90FF',
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'dpi' => self::DPI,
                'defaultFont' => 'Helvetica',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'chroot' => [public_path(), storage_path()],
            ]);

        $filename = 'print-batches/batch-'.$batch->id.'.pdf';
        Storage::disk('public')->put($filename, $pdf->output());

        return $filename;
    }

    /**
     * Prepare data for card template
     */
    private function prepareCardData(IdCard $idCard, bool $forBrowser = false): array
    {
        $beneficiary = $idCard->cardable;

        $beneficiary->loadMissing([
            'deceased.zone.coordinator',
        ]);

        $zone = $beneficiary->deceased?->zone;
        $coordinator = $zone?->coordinator;

        if (! $beneficiary) {
            throw new \Exception("Beneficiary not found for ID Card: {$idCard->card_number}");
        }

        $isWidow = $idCard->cardable_type === Widow::class;
        $gender = null;

        if (! $isWidow) {
            $gender = $beneficiary->gender instanceof \App\Enums\Gender
                ? $beneficiary->gender->getLabel()
                : (is_string($beneficiary->gender) ? $beneficiary->gender : 'N/A');
        } else {
            $gender = 'Female';
        }

        $logoPath = storage_path('app/public/logos/gof_logo.jpeg');
        if (! file_exists($logoPath)) {
            $logoPath = public_path('images/garko-logo.png');
        }

        $fallbackAvatar = file_exists(public_path('images/default-avatar.png'))
            ? public_path('images/default-avatar.png')
            : null;

        return [
            'foundation_logo' => $forBrowser
                ? $this->browserImageSource($logoPath)
                : (file_exists($logoPath) ? $logoPath : null),
            'foundation_name' => 'Garko Orphans Foundation',
            'card_type' => $isWidow ? 'WIDOW ID CARD' : 'ORPHAN ID CARD',
            'card_number' => $idCard->card_number,
            'photo_url' => $forBrowser
                ? $this->publicDiskImageUrl($beneficiary->picture_url, $this->browserImageSource($fallbackAvatar))
                : $this->publicDiskImagePath($beneficiary->picture_url, $fallbackAvatar),
            'full_name' => $beneficiary->full_name ?? 'N/A',
            'nin' => $this->maskNin($beneficiary->nin),
            'reg_no' => $beneficiary->reg_no ?? 'N/A',
            'date_of_birth' => (! $isWidow && isset($beneficiary->birth_date)) ? $beneficiary->birth_date?->format('M d, Y') : null,
            'age' => $isWidow ? null : ($beneficiary->age ?? null),
            'gender' => $gender,
            'address' => Str::limit($beneficiary->address ?? 'N/A', 60),
            'zone' => $zone?->name ?? 'N/A',
            'coordinator_name' => $coordinator?->name ?? 'N/A',
            'coordinator_phone' => $coordinator?->phone ?? $coordinator?->phone_number ?? 'N/A',
//            'zone' => $beneficiary->zone?->name ?? 'N/A',
//            'coordinator_name' => $beneficiary->zone?->coordinator_name ?? 'N/A',
//            'coordinator_phone' => $beneficiary->zone?->coordinator_phone ?? 'N/A',
            'foundation_address' => 'Shop No.1, Garko Juma\'at Mosque, Garko Local Government, Kano',
            'issue_date' => $idCard->issued_at?->format('M d, Y') ?? now()->format('M d, Y'),
            'expiry_date' => $idCard->expires_at?->format('M d, Y'),
            'qr_code' => $forBrowser
                ? $this->publicDiskImageUrl($idCard->qr_code_path)
                : $this->publicDiskImagePath($idCard->qr_code_path),
            'signature_text' => 'Authorized Signature',
            'background_color' => $isWidow ? '#FFF8F0' : '#F0F8FF',
            'accent_color' => $isWidow ? '#8B4513' : '#1E90FF',
        ];
    }

    private function publicDiskImagePath(mixed $value, ?string $fallback = null): ?string
    {
        $path = $this->normalizeStoredPath($value);

        if (! $path) {
            return $fallback;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (file_exists($path)) {
            return $path;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->path($path);
        }

        return $fallback;
    }

    private function publicDiskImageUrl(mixed $value, ?string $fallback = null): ?string
    {
        $path = $this->normalizeStoredPath($value);

        if (! $path) {
            return $fallback;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return $fallback;
    }

    private function normalizeStoredPath(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = reset($value) ?: null;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (Str::startsWith($value, ['/storage/', 'storage/'])) {
            return ltrim(Str::after($value, 'storage/'), '/');
        }

        $publicUrl = Storage::disk('public')->url('');

        if (Str::startsWith($value, $publicUrl)) {
            return ltrim(Str::after($value, $publicUrl), '/');
        }

        return $value;
    }

    private function browserImageSource(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, public_path())) {
            return asset(Str::after($path, public_path().DIRECTORY_SEPARATOR));
        }

        if (Str::startsWith($path, storage_path('app/public'))) {
            return Storage::disk('public')->url(ltrim(Str::after($path, storage_path('app/public')), DIRECTORY_SEPARATOR));
        }

        return $path;
    }

    private function trimTrailingBlankPages(\Barryvdh\DomPDF\PDF $pdf): void
    {
        $pdf->render();

        $canvas = $pdf->getDomPDF()->getCanvas();

        if (! $canvas instanceof \Dompdf\Adapter\CPDF || $canvas->get_page_count() <= 1) {
            return;
        }

        try {
            $canvasReflection = new \ReflectionClass($canvas);
            $cpdfProperty = $canvasReflection->getProperty('_pdf');
            $cpdfProperty->setAccessible(true);
            $cpdf = $cpdfProperty->getValue($canvas);

            $pagesNodeId = $cpdf->currentNode;
            $pageIds = $cpdf->objects[$pagesNodeId]['info']['pages'] ?? [];

            while (count($pageIds) > 1) {
                $lastPageId = end($pageIds);
                $contentIds = $cpdf->objects[$lastPageId]['info']['contents'] ?? [];

                if (! $this->isBlankPdfPageContent($cpdf, $contentIds)) {
                    break;
                }

                array_pop($pageIds);
                $this->popCanvasPageReference($canvas, $canvasReflection);
            }

            $cpdf->objects[$pagesNodeId]['info']['pages'] = array_values($pageIds);
            $cpdf->numPages = count($pageIds);
            $this->setCanvasPageCount($canvas, $canvasReflection, count($pageIds));
        } catch (\Throwable) {
            // If DomPDF internals change, keep the rendered PDF instead of failing card generation.
        }
    }

    private function isBlankPdfPageContent(\Dompdf\Cpdf $cpdf, array $contentIds): bool
    {
        $content = '';

        foreach ($contentIds as $contentId) {
            $content .= $cpdf->objects[$contentId]['c'] ?? '';
        }

        $content = trim($content);

        if ($content === '') {
            return true;
        }

        return strlen($content) < 500
            && ! preg_match('/\b(?:BT|Tj|TJ|Do)\b/', $content);
    }

    private function popCanvasPageReference(\Dompdf\Adapter\CPDF $canvas, \ReflectionClass $canvasReflection): void
    {
        $pagesProperty = $canvasReflection->getProperty('_pages');
        $pagesProperty->setAccessible(true);
        $pages = $pagesProperty->getValue($canvas);
        array_pop($pages);
        $pagesProperty->setValue($canvas, $pages);
    }

    private function setCanvasPageCount(\Dompdf\Adapter\CPDF $canvas, \ReflectionClass $canvasReflection, int $count): void
    {
        foreach (['_page_count', '_page_number'] as $propertyName) {
            $property = $canvasReflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue($canvas, $count);
        }
    }

    /**
     * Mask NIN for privacy (show last 4 digits only)
     */
    private function maskNin(?string $nin): string
    {
        if (! $nin) {
            return 'N/A';
        }

        return str_repeat('•', strlen($nin) - 4).substr($nin, -4);
    }
}
