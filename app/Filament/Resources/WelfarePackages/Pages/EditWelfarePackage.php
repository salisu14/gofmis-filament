<?php

namespace App\Filament\Resources\WelfarePackages\Pages;

use App\Filament\Resources\WelfarePackages\WelfarePackageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditWelfarePackage extends EditRecord
{
    protected static string $resource = WelfarePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
