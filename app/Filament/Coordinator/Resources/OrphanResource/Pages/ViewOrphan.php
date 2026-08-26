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
