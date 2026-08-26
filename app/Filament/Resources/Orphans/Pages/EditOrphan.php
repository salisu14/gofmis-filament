<?php

namespace App\Filament\Resources\Orphans\Pages;

use App\Filament\Resources\Orphans\OrphanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOrphan extends EditRecord
{
    protected static string $resource = OrphanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn ($record) => $record->status !== \App\Enums\OrphanStatus::ARCHIVED && $record->is_eligible && ! $record->hasHistoricalRecords()),
            ForceDeleteAction::make()
                ->visible(fn ($record) => $record->status !== \App\Enums\OrphanStatus::ARCHIVED && $record->is_eligible && ! $record->hasHistoricalRecords()),
            RestoreAction::make(),
        ];
    }
}
