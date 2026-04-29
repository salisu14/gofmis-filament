<?php
// app/Filament\Coordinator\Resources\LoanRequestResource/Pages/ViewLoanRequest.php

namespace App\Filament\Coordinator\Resources\LoanRequestResource\Pages;

use App\Filament\Coordinator\Resources\LoanRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewLoanRequest extends ViewRecord
{
    protected static string $resource = LoanRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
//                ->visible(fn($record) => $record->status === \App\Enums\BeneficiaryStatus::PENDING),
        ];
    }
}
