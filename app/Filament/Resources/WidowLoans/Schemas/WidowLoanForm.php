<?php

namespace App\Filament\Resources\WidowLoans\Schemas;

use App\Enums\LoanRepaymentFrequency;
use App\Enums\WidowLoanStatus;
use App\Models\Widow;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WidowLoanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Loan Application')
                    ->description('Identify the borrower and the primary terms of the loan.')
                    ->icon('heroicon-m-document-currency-dollar')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('widow_id')
                                ->label('Widow (Borrower)')
                                ->relationship('widow', 'full_name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabledOn('edit')
                                ->hint(function ($state) {
                                    if (!$state) return null;
                                    $widow = Widow::find($state);
                                    if ($widow?->is_married) return '⚠️ Remarried (Ineligible for new loans)';
                                    return $widow?->canApplyForLoan() ? '✅ Eligible' : '❌ Active Loan Exists';
                                }),

                            TextInput::make('purpose')
                                ->label('Loan Purpose')
                                ->placeholder('e.g., Small business expansion')
                                ->required()
                                ->maxLength(255),

                            Select::make('bank_account_id')
                                ->label('Disbursement Bank Account')
                                ->relationship('bankAccount', 'account_name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->account_name} ({$record->account_number})")
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('principal_amount')
                                ->numeric()
                                ->prefix('₦')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn($state, $set) => $set('total_payable', (float)$state)),

                            Select::make('repayment_frequency')
                                ->label('Repayment Frequency')
                                ->options(LoanRepaymentFrequency::class)
                                ->required()
                                ->default(LoanRepaymentFrequency::WEEKLY)
                                ->native(false)
                                ->live(),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('duration_months')
                                ->label('Duration (Months)')
                                ->helperText('Number of months for the loan term.')
                                ->numeric()
                                ->default(12)
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(function ($state, $get, $set) {
                                    $principal = (float)$get('total_payable');
                                    $duration = (int)$state;
                                    $freq = $get('repayment_frequency');
                                    if ($principal > 0 && $duration > 0) {
                                        $intervals = $freq === LoanRepaymentFrequency::WEEKLY->value || $freq === 'weekly'
                                            ? $duration * 4
                                            : $duration;
                                        $set('installment_amount', round($principal / $intervals, 2));
                                    }
                                }),

                            TextInput::make('total_payable')
                                ->label('Total Payable')
                                ->numeric()
                                ->prefix('₦')
                                ->readOnly(),

                            TextInput::make('installment_amount')
                                ->label('Per Installment')
                                ->numeric()
                                ->prefix('₦')
                                ->readOnly()
                                ->helperText('Auto-calculated from frequency & duration.'),
                        ]),
                    ]),

                Section::make('Approval & Status')
                    ->icon('heroicon-m-check-badge')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('status')
                                ->options(WidowLoanStatus::class)
                                ->required()
                                ->default(WidowLoanStatus::DRAFT->value)
                                ->native(false)
                                ->disabled()
                                ->dehydrated(),

                            DateTimePicker::make('disbursed_at')
                                ->label('Disbursement Date')
                                ->native(false),

                            Toggle::make('fully_repaid')
                                ->label('Mark as Fully Repaid')
                                ->disabled(),
                        ]),

                        FileUpload::make('loan_agreement_url')
                            ->label('Signed Loan Agreement')
                            ->directory('loan-documents')
                            ->disk('public')
                            ->acceptedFileTypes(['application/pdf']),

                        Textarea::make('reject_reason')
                            ->label('Rejection Reason')
                            ->visible(fn($get) => $get('status') === WidowLoanStatus::REJECTED->value)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
