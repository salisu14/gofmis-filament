<?php

namespace App\Filament\Resources\WelfarePackages\Pages;

use App\Filament\Resources\WelfarePackages\WelfarePackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWelfarePackages extends ListRecords
{
    protected static string $resource = WelfarePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
