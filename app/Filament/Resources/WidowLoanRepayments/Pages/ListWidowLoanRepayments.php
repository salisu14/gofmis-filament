<?php

namespace App\Filament\Resources\WidowLoanRepayments\Pages;

use App\Filament\Resources\WidowLoanRepayments\WidowLoanRepaymentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWidowLoanRepayments extends ListRecords
{
    protected static string $resource = WidowLoanRepaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
