<?php

namespace App\Filament\Resources\WelfarePackages\Pages;

use App\Filament\Resources\WelfarePackages\WelfarePackageResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWelfarePackage extends ViewRecord
{
    protected static string $resource = WelfarePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }
}
