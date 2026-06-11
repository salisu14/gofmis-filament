<?php

namespace App\Filament\Resources\WidowLoans\Pages;

use App\Filament\Resources\WidowLoans\WidowLoanResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWidowLoan extends ViewRecord
{
    protected static string $resource = WidowLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            // 1. Coordinator submits draft loan for super-admin approval
            \App\Filament\Actions\SubmitForApprovalAction::make(),

            // 2. Super admin approves or rejects the submitted loan
            \App\Filament\Actions\ApproveWidowLoanAction::make(),
            \App\Filament\Actions\RejectWidowLoanAction::make(),

            // 3. After approval, finance disburses the funds from the bank
            \App\Filament\Actions\DisburseWidowLoanAction::make(),

            // 4. Confirm the widow has physically collected the funds
            \App\Filament\Actions\MarkLoanCollectedAction::make(),


            Action::make('downloadStatement')
                ->label('Download Statement')
                ->icon('heroicon-m-document-text')
                ->color('info')
                ->url(fn ($record) => route('loans.statement.download', $record))
                ->openUrlInNewTab()
                ->visible(fn ($record) => $record->repayments()->exists()),
        ];
    }
}
