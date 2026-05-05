<?php
// app/Filament/Resources/IdCardResource/Pages/PreviewIdCard.php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use App\Models\IdCard;
use App\Services\IdCardPDFService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Widow;

class PreviewIdCard extends Page
{
    protected static string $resource = IdCardResource::class;
    protected static ?string $title = 'Preview ID Card';

    public IdCard $record;

    public function mount(IdCard $record): void
    {
        $this->record = $record;
    }

    public function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('download')
                ->label('Download PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn(): string => route('id-cards.download', ['idCard' => $this->record]))
                ->openUrlInNewTab(),
        ];
    }

    public function getViewData(): array
    {
        try {
            $card = $this->record;
            $beneficiary = $card->cardable;

            $isWidow = $card->cardable_type === Widow::class;

        // logo fallback
        $logoPath = storage_path('app/public/logos/gof_logo.jpeg');
        if (!file_exists($logoPath)) {
            $logoPath = public_path('images/garko-logo.png');
        }

        $photo = null;
        if ($beneficiary) {
            $photo = ($beneficiary->picture_url && Storage::disk('public')->exists($beneficiary->picture_url))
                ? Storage::disk('public')->path($beneficiary->picture_url)
                : (file_exists(public_path('images/default-avatar.png')) ? public_path('images/default-avatar.png') : null);
        }

        $gender = $isWidow ? 'Female' : ($beneficiary?->gender instanceof \App\Enums\Gender
            ? $beneficiary->gender->getLabel()
            : (is_string($beneficiary?->gender) ? $beneficiary->gender : 'N/A'));

        $data = [
            'pdf_url' => route('id-cards.preview', ['card' => $this->record]),
            'record' => $this->record,
            'foundation_logo' => file_exists($logoPath) ? $logoPath : null,
            'foundation_name' => 'Garko Orphans Foundation',
            'card_type' => $isWidow ? 'WIDOW ID CARD' : 'ORPHAN ID CARD',
            'card_number' => $card->card_number,
            'photo_url' => $photo,
            'full_name' => $beneficiary->full_name ?? 'N/A',
            'nin' => $beneficiary->nin ?? 'N/A',
            'reg_no' => $beneficiary->reg_no ?? 'N/A',
            'date_of_birth' => $beneficiary?->birth_date?->format('M d, Y') ?? null,
            'age' => $beneficiary->age ?? null,
            'gender' => $gender,
            'address' => Str::limit($beneficiary->address ?? 'N/A', 60),
            'zone' => $beneficiary->zone?->name ?? 'N/A',
            'coordinator_name' => $beneficiary->zone?->coordinator_name ?? 'N/A',
            'coordinator_phone' => $beneficiary->zone?->coordinator_phone ?? 'N/A',
            'foundation_address' => 'Shop No.1, Garko Juma\'at Mosque, Garko Local Government, Kano',
            'issue_date' => $card->issued_at?->format('M d, Y') ?? now()->format('M d, Y'),
            'expiry_date' => $card->expires_at?->format('M d, Y'),
            'qr_code' => ($card->qr_code_path && Storage::disk('public')->exists($card->qr_code_path))
                ? Storage::disk('public')->path($card->qr_code_path)
                : null,
            'signature_text' => 'Authorized Signature',
            'background_color' => $isWidow ? '#FFF8F0' : '#F0F8FF',
            'accent_color' => $isWidow ? '#8B4513' : '#1E90FF',
        ];

            return $data;
        } catch (\Throwable $e) {
            \Log::error('PreviewIdCard getViewData error: ' . $e->getMessage(), ['exception' => $e]);

            return [
                'pdf_url' => route('id-cards.preview', ['card' => $this->record]),
                'record' => $this->record,
                'preview_error' => $e->getMessage(),
            ];
        }
    }
}
