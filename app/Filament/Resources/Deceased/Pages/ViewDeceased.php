<?php

namespace App\Filament\Resources\Deceased\Pages;

use App\Filament\Resources\Deceased\DeceasedResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDeceased extends ViewRecord
{
    protected static string $resource = DeceasedResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewDeathCert')
                ->label('Preview Death Certificate')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (\App\Models\Deceased $record): string => route('deceased.death-certificate.preview', ['deceased' => $record]))
                ->openUrlInNewTab()
                ->visible(fn (\App\Models\Deceased $record): bool => filled($record->death_cert_url) && (\Illuminate\Support\Facades\Storage::disk('local')->exists($record->death_cert_url) || \Illuminate\Support\Facades\Storage::disk('public')->exists($record->death_cert_url))),

            Action::make('downloadDeathCert')
                ->label('Download Death Certificate')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn (\App\Models\Deceased $record): string => route('deceased.death-certificate.download', ['deceased' => $record]))
                ->openUrlInNewTab()
                ->visible(fn (\App\Models\Deceased $record): bool => filled($record->death_cert_url) && (\Illuminate\Support\Facades\Storage::disk('local')->exists($record->death_cert_url) || \Illuminate\Support\Facades\Storage::disk('public')->exists($record->death_cert_url))),

            EditAction::make(),
        ];
    }
}
