<?php

namespace App\Filament\Resources\EducationFeeInvoices\RelationManagers;

use App\Exceptions\InsufficientBankBalanceException;
use App\Models\BankAccount;
use App\Models\EducationFeeInvoice;
use App\Models\EducationFeePayment;
use App\Models\Transaction;
use App\Filament\Resources\EducationFeeInvoices\EducationFeeInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';
    protected static ?string $relatedResource = EducationFeeInvoiceResource::class;

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?string $title = 'Payment History';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Payment Details')
                    ->schema([
                        TextInput::make('reference')
                            ->label('Payment Ref')
                            ->placeholder('Generated automatically')
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('bank_account_id')
                            ->label('Paying Bank Account')
                            ->relationship(
                                name: 'bankAccount',
                                titleAttribute: 'account_name',
                                modifyQueryUsing: fn ($query) => $query->dedicatedTo(BankAccount::USAGE_EDUCATION)
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->helperText('The selected bank account is debited when this payment is recorded.'),

                        TextInput::make('amount')
                            ->numeric()
                            ->prefix('₦')
                            ->required()
                            ->maxValue(fn () => max(0, $this->getOwnerRecord()->balance))
                            ->disabledOn('edit')
                            ->helperText(fn () => 'Outstanding balance: ₦'.number_format(max(0, $this->getOwnerRecord()->balance), 2)),

                        DatePicker::make('payment_date')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'bank_deposit' => 'Bank Deposit',
                                'transfer' => 'Bank Transfer',
                                'pos' => 'POS',
                            ])
                            ->required()
                            ->native(false),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('amount')
                    ->money('NGN')
                    ->summarize(Sum::make()->money('NGN')->label('Total Paid')),

                TextColumn::make('bankAccount.account_name')
                    ->label('Bank')
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('reference')
                    ->label('Ref')
                    ->searchable()
                    ->copyable()
                    ->placeholder('Generated'),
            ])
            ->defaultSort('payment_date', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Record Payment')
                    ->icon('heroicon-m-banknotes')
                    ->modalHeading('New Payment Entry')
                    ->modalWidth('xl')
                    ->visible(fn () => ! in_array($this->getOwnerRecord()->status, ['paid', 'cancelled'], true))
                    ->using(function (array $data): EducationFeePayment {
                        return DB::transaction(function () use ($data): EducationFeePayment {
                            $invoice = EducationFeeInvoice::query()
                                ->whereKey($this->getOwnerRecord()->getKey())
                                ->lockForUpdate()
                                ->firstOrFail();
                            $amount = (float) $data['amount'];

                            if ($invoice->status === 'cancelled') {
                                throw ValidationException::withMessages([
                                    'amount' => 'Payments cannot be recorded against a cancelled invoice.',
                                ]);
                            }

                            if ($amount > (float) $invoice->balance) {
                                throw ValidationException::withMessages([
                                    'amount' => 'This payment is higher than the outstanding balance.',
                                ]);
                            }

                            $bank = BankAccount::query()
                                ->whereKey($data['bank_account_id'])
                                ->lockForUpdate()
                                ->firstOrFail();
                            $bank->ensureDedicatedTo(BankAccount::USAGE_EDUCATION, 'education fee payments');

                            try {
                                $bank->debit($amount);
                            } catch (InsufficientBankBalanceException $exception) {
                                throw ValidationException::withMessages([
                                    'bank_account_id' => $exception->getMessage(),
                                ]);
                            }

                            /** @var EducationFeePayment $payment */
                            $payment = $invoice->payments()->create($data);

                            Transaction::create([
                                'bank_account_id' => $bank->id,
                                'reference' => $payment->reference,
                                'description' => "Education fee payment for {$invoice->education?->orphan?->full_name} ({$invoice->period})",
                                'amount' => $payment->amount,
                                'type' => 'education_fee_payment',
                                'date' => $payment->payment_date,
                                'is_system' => true,
                                'transactionable_type' => EducationFeePayment::class,
                                'transactionable_id' => $payment->id,
                            ]);

                            return $payment;
                        });
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (EducationFeePayment $record) => ! $record->transaction()->exists()),
                DeleteAction::make()
                    ->visible(fn (EducationFeePayment $record) => ! $record->transaction()->exists()),
            ]);
    }
}
