<?php
// app/Filament/Coordinator/Resources/LoanRequestResource/Pages/ListLoanRequests.php

namespace App\Filament\Coordinator\Resources\LoanRequestResource\Pages;

use App\Filament\Coordinator\Resources\LoanRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoanRequests extends ListRecords
{
    protected static string $resource = LoanRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Loan Request'),
        ];
    }
}
