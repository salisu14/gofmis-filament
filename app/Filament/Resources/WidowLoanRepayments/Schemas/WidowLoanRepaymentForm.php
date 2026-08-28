<?php

namespace App\Filament\Resources\WidowLoanRepayments\Schemas;

use App\Enums\WidowLoanStatus;
use App\Models\BankAccount;
use App\Models\WidowLoan;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                            ->default(fn () => request()->query('widow_loan_id'))
                            ->live()
                            ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                $loanId = $state ?: request()->query('widow_loan_id');
                                if ($loanId && ! $get('bank_account_id')) {
                                    self::hydrateReceivingBankAccount($loanId, $set, $get);
                                }
                            })
                            // Auto-fill the receiving bank account when a loan is selected
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                if ($state) {
                                    self::hydrateReceivingBankAccount($state, $set, $get);
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
                                        'cash' => 'Cash',
                                        'transfer' => 'Bank Transfer',
                                        'deduction' => 'Monthly Deduction',
                                    ])
                                    ->required()
                                    ->default('cash'),

                                Select::make('bank_account_id')
                                    ->label('Receiving Bank Account')
                                    ->options(function (callable $get) {
                                        $query = BankAccount::query()
                                            ->dedicatedTo(BankAccount::USAGE_WIDOW_LOAN_REPAYMENT);

                                        $loanId = $get('widow_loan_id') ?? request()->query('widow_loan_id');
                                        if ($loanId) {
                                            $loan = WidowLoan::find($loanId);
                                            if ($loan && ($loan->repayment_bank_id || $loan->bank_account_id)) {
                                                $linkedId = $loan->repayment_bank_id ?? $loan->bank_account_id;
                                                $query->orWhere('id', $linkedId);
                                            }
                                        }

                                        return $query->orderBy('account_name')
                                            ->get()
                                            ->mapWithKeys(fn (BankAccount $account) => [
                                                $account->id => "{$account->account_name} - {$account->account_number}",
                                            ])
                                            ->toArray();
                                    })
                                    ->default(function (callable $get) {
                                        $loanId = $get('widow_loan_id') ?? request()->query('widow_loan_id');
                                        if ($loanId) {
                                            $loan = WidowLoan::find($loanId);
                                            if ($loan) {
                                                $targetBankId = $loan->repayment_bank_id ?? $loan->bank_account_id;
                                                if ($targetBankId) {
                                                    return $targetBankId;
                                                }
                                            }
                                        }
                                        $eligibleAccounts = BankAccount::query()
                                            ->dedicatedTo(BankAccount::USAGE_WIDOW_LOAN_REPAYMENT)
                                            ->pluck('id');
                                        if ($eligibleAccounts->count() === 1) {
                                            return $eligibleAccounts->first();
                                        }

                                        return null;
                                    })
                                    ->afterStateHydrated(function ($state, callable $set, callable $get) {
                                        if (! $state) {
                                            $loanId = $get('widow_loan_id') ?? request()->query('widow_loan_id');
                                            self::hydrateReceivingBankAccount($loanId, $set, $get);
                                        }
                                    })
                                    ->getOptionLabelUsing(
                                        fn ($value) => BankAccount::find($value)?->display_name
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

    protected static function hydrateReceivingBankAccount(mixed $loanId, callable $set, callable $get): void
    {
        $loan = $loanId ? WidowLoan::find($loanId) : null;
        if ($loan) {
            $targetBankId = $loan->repayment_bank_id ?? $loan->bank_account_id;
            if ($targetBankId && BankAccount::where('id', $targetBankId)->exists()) {
                $set('bank_account_id', $targetBankId);

                return;
            }
        }

        $eligibleAccounts = BankAccount::query()
            ->dedicatedTo(BankAccount::USAGE_WIDOW_LOAN_REPAYMENT)
            ->pluck('id');

        if ($eligibleAccounts->count() === 1) {
            $set('bank_account_id', $eligibleAccounts->first());
        }
    }
}
