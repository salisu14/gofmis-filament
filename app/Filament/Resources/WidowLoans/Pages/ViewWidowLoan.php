<?php

namespace App\Filament\Resources\WidowLoans\Pages;

use App\Filament\Resources\WidowLoans\WidowLoanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWidowLoan extends ViewRecord
{
    protected static string $resource = WidowLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            \App\Filament\Actions\SubmitForApprovalAction::make(),
            \App\Filament\Actions\ApproveWidowLoanAction::make(),
            \App\Filament\Actions\RejectWidowLoanAction::make(),
        ];
    }
}
