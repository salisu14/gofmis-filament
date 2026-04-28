<?php
// app/Filament/Resources/IdCardResource/Pages/PreviewIdCard.php

namespace App\Filament\Resources\IdCards\Pages;

use App\Filament\Resources\IdCards\IdCardResource;
use App\Models\IdCard;
use App\Services\IdCardPDFService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;

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
        return [
            'pdf_url' => route('id-cards.download', ['idCard' => $this->record, 'preview' => 1]),
            'record' => $this->record,
        ];
    }
}
