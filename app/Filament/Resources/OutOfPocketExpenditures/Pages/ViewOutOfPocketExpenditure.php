<?php

namespace App\Filament\Resources\OutOfPocketExpenditures\Pages;

use App\Filament\Resources\OutOfPocketExpenditures\OutOfPocketExpenditureResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOutOfPocketExpenditure extends ViewRecord
{
    protected static string $resource = OutOfPocketExpenditureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn ($record) => $record->isDraft()),
        ];
    }
}
