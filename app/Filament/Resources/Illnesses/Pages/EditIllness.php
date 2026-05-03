<?php

namespace App\Filament\Resources\Illnesses\Pages;

use App\Filament\Resources\Illnesses\IllnessResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditIllness extends EditRecord
{
    protected static string $resource = IllnessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
