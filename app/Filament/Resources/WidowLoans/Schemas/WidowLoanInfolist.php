<?php

namespace App\Filament\Resources\WidowLoans\Schemas;

use App\Enums\WidowLoanStatus;
use App\Models\WidowLoan;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WidowLoanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Loan Identity')
                    ->description('General overview of the beneficiary and the loan intent.')
                    ->icon('heroicon-m-information-circle')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('widow.full_name')
                                ->label('Beneficiary')
                                ->weight('bold')
                                ->size('lg')
                                ->color('primary'),

                            TextEntry::make('status')
                                ->badge(),

                            TextEntry::make('purpose')
                                ->label('Loan Purpose')
                                ->placeholder('No purpose defined'),

                            TextEntry::make('duration_months')
                                ->label('Term')
                                ->suffix(' Months')
                                ->color('gray'),
                        ]),
                    ]),

                Section::make('Financial Accounting')
                    ->description('Real-time tracking of the loan amounts and repayment progress.')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('principal_amount')
                                ->label('Principal')
                                ->money('NGN'),

                            TextEntry::make('total_payable')
                                ->label('Total Payable')
                                ->money('NGN'),

                            TextEntry::make('total_paid')
                                ->label('Total Repaid')
                                ->money('NGN')
                                ->color('success')
                                ->weight('bold')
                                ->state(fn(WidowLoan $record) => $record->repayments()->sum('amount')),

                            TextEntry::make('outstanding_balance')
                                ->label('Remaining Balance')
                                ->money('NGN')
                                ->state(fn(WidowLoan $record) => (float)$record->total_payable - (float)$record->repayments()->sum('amount'))
                                ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                                ->weight('bold'),
                        ]),
                    ]),

                Section::make('Disbursement & Documentation')
                    ->description('Verification of fund release and legal agreements.')
                    ->icon('heroicon-m-scale')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('disbursed_at')
                                ->label('Disbursed On')
                                ->dateTime()
                                ->placeholder('Pending Disbursement'),

                            IconEntry::make('fully_repaid')
                                ->label('Settlement Status')
                                ->boolean(),

                            TextEntry::make('loan_agreement_url')
                                ->label('Agreement File')
                                ->placeholder('No Document Attached')
                                ->url(fn($record) => $record->loan_agreement_url ? asset('storage/' . $record->loan_agreement_url) : null)
                                ->openUrlInNewTab()
                                ->color('primary')
                                ->icon('heroicon-m-paper-clip'),
                        ]),

                        TextEntry::make('reject_reason')
                            ->label('Rejection Narrative')
                            ->visible(fn($record) => $record->status === WidowLoanStatus::REJECTED)
                            ->columnSpanFull()
                            ->placeholder('No specific reason provided'),
                    ]),

                Section::make('Audit Metadata')
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('id')
                                ->label('Internal ID')
                                ->fontFamily('mono')
                                ->copyable(),
                            TextEntry::make('created_at')
                                ->label('Date Applied')
                                ->dateTime(),
                            TextEntry::make('updated_at')
                                ->label('Last Modified')
                                ->dateTime(),
                        ]),
                    ]),
            ]);
    }
}
