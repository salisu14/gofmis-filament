<?php

namespace App\Filament\Coordinator\Resources\ProjectResource\Pages;
use App\Enums\ProjectStatus;
use App\Filament\Coordinator\Resources\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn($record) => $record->status === ProjectStatus::PLANNING),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
