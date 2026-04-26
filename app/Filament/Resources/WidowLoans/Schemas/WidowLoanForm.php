<?php

namespace App\Filament\Resources\WidowLoans\Schemas;

use App\Enums\WidowLoanStatus;
use App\Models\Widow;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('principal_amount')
                                ->numeric()
                                ->prefix('₦')
                                ->required()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, $set) => $set('total_payable', (float)$state)),

                            Select::make('repayment_frequency')
                                ->label('Repayment Frequency')
                                ->options([
                                    'weekly' => 'Weekly',
                                    'monthly' => 'Monthly',
                                ])
                                ->required()
                                ->default('weekly')
                                ->native(false),
                        ]),

                        Grid::make(3)->schema([
                            TextInput::make('duration_months')
                                ->label('Duration (Units)')
                                ->helperText('Number of weeks/months based on frequency.')
                                ->numeric()
                                ->default(12)
                                ->required(),

                            TextInput::make('total_payable')
                                ->label('Total Payable')
                                ->numeric()
                                ->prefix('₦')
                                ->readOnly(),

                            TextInput::make('installment_amount')
                                ->label('Per Installment')
                                ->numeric()
                                ->prefix('₦')
                                ->placeholder('Calculated on approval')
                                ->disabled(),
                        ]),
                    ]),

                Section::make('Approval & Status')
                    ->icon('heroicon-m-check-badge')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('status')
                                ->options(WidowLoanStatus::class)
                                ->required()
                                ->default(WidowLoanStatus::PENDING->value)
                                ->native(false),

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
                            ->visible(fn ($get) => $get('status') === WidowLoanStatus::REJECTED->value)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
