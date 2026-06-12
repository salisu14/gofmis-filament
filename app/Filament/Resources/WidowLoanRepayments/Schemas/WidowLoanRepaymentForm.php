<?php

namespace App\Filament\Resources\WidowLoanRepayments\Schemas;

use App\Models\WidowLoan;
use App\Enums\WidowLoanStatus;
use App\Models\BankAccount;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WidowLoanRepaymentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Loan Selection')
                    ->description('Select the active loan you are recording a payment for.')
                    ->schema([
                        Select::make('widow_loan_id')
                            ->label('Widow / Loan Purpose')
                            ->relationship(
                                name: 'widowLoan',
                                titleAttribute: 'id',
                                modifyQueryUsing: fn ($query) => $query
                                    ->with('widow')
                                    ->where('status', WidowLoanStatus::DISBURSED->value)
                                    ->whereNotNull('collected_at')
                                    ->where('fully_repaid', false)
                                    ->where('outstanding_balance', '>', 0),
                            )
                            ->getOptionLabelFromRecordUsing(fn (WidowLoan $record) => "{$record->widow->full_name} — {$record->purpose}")
                            ->searchable(['purpose']) // Allows searching by loan purpose
                            ->preload()
                            ->required()
                            ->live()
                            // Auto-fill the receiving bank account when a loan is selected
                            ->afterStateUpdated(function ($state, callable $set) {
                                $loan = WidowLoan::find($state);
                                if ($loan) {
                                    $set('bank_account_id', $loan->repayment_bank_id ?? $loan->bank_account_id);
                                }
                            }),
                    ]),

                Section::make('Payment Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('amount')
                                    ->label('Amount Paid')
                                    ->required()
                                    ->numeric()
                                    ->prefix('₦')
                                    ->minValue(1)
                                    ->step(0.01)
                                    // 🔒 Lock the amount if we are editing an existing record
                                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                                    ->dehydrated() // Important! Ensures the disabled value is still sent to the server/save action
                                    ->maxValue(fn (callable $get) => WidowLoan::find($get('widow_loan_id'))?->outstanding_balance ?? 999999),

                                DatePicker::make('paid_at')
                                    ->label('Date Paid')
                                    ->required()
                                    ->default(now())
                                    ->maxDate(now())
                                    ->native(false)
                                    // 🔒 Lock the date if we are editing an existing record
                                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                                    ->dehydrated(),
                            ]),
                        Grid::make(2)
                            ->schema([
                                Select::make('payment_method')
                                    ->label('Payment Method')
                                    ->options([
                                        'cash'      => 'Cash',
                                        'transfer'  => 'Bank Transfer',
                                        'deduction' => 'Monthly Deduction',
                                    ])
                                    ->required()
                                    ->default('cash'),

                                Select::make('bank_account_id')
                                    ->label('Receiving Bank Account')
                                    ->relationship(
                                        name: 'bankAccount',
                                        titleAttribute: 'account_name',
                                        modifyQueryUsing: fn ($query) => $query->dedicatedTo(BankAccount::USAGE_WIDOW_LOAN_REPAYMENT)
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required(),
                            ]),
                        Textarea::make('notes')
                            ->columnSpanFull()
                            ->placeholder('Optional: e.g. Payment for Week 3'),
                    ]),

                // Hidden/System fields - Not shown to the user, handled by the system
                TextInput::make('receipt_number')->hidden(),
                TextInput::make('transaction_id')->hidden(),
            ]);
    }
}
