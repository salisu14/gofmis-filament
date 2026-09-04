<?php

namespace App\Filament\Resources\OutOfPocketExpenditures\Pages;

use App\Filament\Resources\OutOfPocketExpenditures\OutOfPocketExpenditureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOutOfPocketExpenditure extends EditRecord
{
    protected static string $resource = OutOfPocketExpenditureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => $record->isDraft()),
        ];
    }
}
