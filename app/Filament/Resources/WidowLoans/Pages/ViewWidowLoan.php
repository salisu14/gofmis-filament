<?php

namespace App\Filament\Resources\WidowLoans\Pages;

use App\Filament\Actions\ApproveWidowLoanAction;
use App\Filament\Actions\RejectWidowLoanAction;
use App\Filament\Actions\SubmitForApprovalAction;
use App\Filament\Resources\WidowLoans\WidowLoanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWidowLoan extends ViewRecord
{
    protected static string $resource = WidowLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SubmitForApprovalAction::make(),
            ApproveWidowLoanAction::make(),
            RejectWidowLoanAction::make(),
            EditAction::make(),
        ];
    }
}
