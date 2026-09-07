<?php

namespace App\Filament\Coordinator\Resources\OrphanResource\Pages;

use App\Filament\Coordinator\Resources\OrphanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrphan extends ViewRecord
{
    protected static string $resource = OrphanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('previewBirthCert')
                ->label('Preview Birth Certificate')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn (\App\Models\Orphan $record): string => route('orphans.birth-certificate.preview', ['orphan' => $record]))
                ->openUrlInNewTab()
                ->visible(fn (\App\Models\Orphan $record): bool => filled($record->birth_certificate_path) && (\Illuminate\Support\Facades\Storage::disk('local')->exists($record->birth_certificate_path) || \Illuminate\Support\Facades\Storage::disk('public')->exists($record->birth_certificate_path))),

            \Filament\Actions\Action::make('downloadBirthCert')
                ->label('Download Birth Certificate')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn (\App\Models\Orphan $record): string => route('orphans.birth-certificate.download', ['orphan' => $record]))
                ->openUrlInNewTab()
                ->visible(fn (\App\Models\Orphan $record): bool => filled($record->birth_certificate_path) && (\Illuminate\Support\Facades\Storage::disk('local')->exists($record->birth_certificate_path) || \Illuminate\Support\Facades\Storage::disk('public')->exists($record->birth_certificate_path))),

            \Filament\Actions\Action::make('downloadDossier')
                ->label('Download Dossier')
                ->icon('heroicon-o-document-arrow-down')
                ->color('secondary')
                ->url(fn ($record): string => route('orphans.report.download', ['orphan' => $record]))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
