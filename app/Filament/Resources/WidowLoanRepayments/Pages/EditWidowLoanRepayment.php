<?php

namespace App\Filament\Resources\WidowLoanRepayments\Pages;

use App\Filament\Resources\WidowLoanRepayments\WidowLoanRepaymentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWidowLoanRepayment extends EditRecord
{
    protected static string $resource = WidowLoanRepaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
           // DeleteAction::make(),
        ];
    }
}
