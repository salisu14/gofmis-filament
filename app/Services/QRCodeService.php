<?php
// app/Services/QRCodeService.php

namespace App\Services;

use App\Models\IdCard;
use App\Models\Widow;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class QRCodeService
{
    private PngWriter $writer;

    public function __construct()
    {
        $this->writer = new PngWriter();
    }

    /**
     * Generate a signed verification URL for the QR code
     */
    public function generateVerificationUrl(IdCard $idCard): string
    {
        $expiresAt = $idCard->expires_at ?? now()->addYears(5);

        return URL::signedRoute(
            'id-cards.verify',
            ['card' => $idCard->id],
            $expiresAt
        );
    }

    /**
     * Generate QR code image for an ID card
     */
    public function generateForCard(IdCard $idCard): string
    {
        $verificationUrl = $this->generateVerificationUrl($idCard);

        // Use ErrorCorrectionLevel::H for High (~30% error correction)
        $qrCode = new QrCode(
            data: $verificationUrl,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 400,
            margin: 10,
            foregroundColor: new Color(0, 51, 102),
            backgroundColor: new Color(255, 255, 255)
        );

        $result = $this->writer->write($qrCode);

        // Add foundation logo watermark if exists
        $logoPath = public_path('images/garko-logo.png');
        if (file_exists($logoPath)) {
            $logo = new Logo($logoPath, 60);
            $result = $this->writer->write($qrCode, $logo);
        }

        $filename = 'qr-codes/' . $idCard->card_number . '.png';
        Storage::disk('public')->put($filename, $result->getString());

        return $filename;
    }

    /**
     * Verify a scanned QR code
     */
    public function verify(string $cardId): array
    {
        $idCard = IdCard::with(['cardable', 'template'])->find($cardId);

        if (!$idCard) {
            return ['valid' => false, 'message' => 'Card not found'];
        }

        if ($idCard->status === 'revoked') {
            return [
                'valid' => false,
                'message' => 'This card has been revoked: ' . $idCard->revocation_reason
            ];
        }

        if ($idCard->status === 'expired' || ($idCard->expires_at && $idCard->expires_at->isPast())) {
            return ['valid' => false, 'message' => 'This card has expired'];
        }

        $cardable = $idCard->cardable;

        return [
            'valid' => true,
            'card_number' => $idCard->card_number,
            'type' => $idCard->cardable_type === Widow::class ? 'Widow' : 'Orphan',
            'name' => $cardable->full_name,
            'status' => $idCard->status,
            'issued_at' => $idCard->issued_at->format('F j, Y'),
            'expires_at' => $idCard->expires_at?->format('F j, Y'),
            'photo_url' => $cardable->picture_url ? Storage::url($cardable->picture_url) : null,
        ];
    }
}
