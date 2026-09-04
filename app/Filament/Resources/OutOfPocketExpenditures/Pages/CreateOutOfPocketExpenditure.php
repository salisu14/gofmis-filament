<?php

namespace App\Filament\Resources\OutOfPocketExpenditures\Pages;

use App\Filament\Resources\OutOfPocketExpenditures\OutOfPocketExpenditureResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOutOfPocketExpenditure extends CreateRecord
{
    protected static string $resource = OutOfPocketExpenditureResource::class;
}
