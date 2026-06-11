<?php

namespace App\Filament\Resources\WidowLoans\RelationManagers;

/* -----------------------------
 | 2. LOAN REPAYMENTS MANAGER
 ------------------------------*/

use App\Data\Loan\RecordWidowLoanRepaymentData;
use App\Models\BankAccount;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Services\WidowLoanService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RepaymentsRelationManager extends RelationManager
{
    protected static ?string $model = WidowLoan::class;
    protected static string $relationship = 'repayments';
    protected static ?string $title = 'Actual Repayments';

    public function form(Schema $schema): Schema
    {
        return $schema
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
                    ->maxValue(fn () => $this->ownerRecord?->outstanding_balance ?? 999999),
                DatePicker::make('paid_at')
                    ->label('Date Paid')
                    ->required()
                    ->default(now())
                    ->maxDate(now())
                    ->native(false)
                    // 🔒 Lock the date if we are editing an existing record
                    ->disabled(fn (string $operation): bool => $operation === 'edit')
                    ->dehydrated(),
                Select::make('bank_account_id')
                    ->label('Receiving Bank Account')
                    ->options(
                        BankAccount::query()
                            ->orderBy('account_name')
                            ->get()
                            ->mapWithKeys(fn(BankAccount $bank) => [
                                $bank->id => "{$bank->account_name} ({$bank->account_number})",
                            ])
                            ->toArray()
                    )
                    ->default(fn() => $this->ownerRecord?->repayment_bank_id ?? $this->ownerRecord?->bank_account_id)
                    ->searchable()
                    ->required(),
                Select::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'transfer' => 'Bank Transfer',
                        'deduction' => 'Monthly Deduction',
                    ])
                    ->required(),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('paid_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money('NGN')
                    ->summarize(Sum::make()
                        ->money('NGN')),
                TextColumn::make('running_balance')
                    ->label('Balance After Payment')
                    ->money('NGN')
                    ->alignEnd()
                    ->state(function ($record, $livewire) {
                        // Fallback to principal_amount if total_payable is null
                        $totalPayable = (float) ($livewire->ownerRecord->total_payable ?? $livewire->ownerRecord->principal_amount);

                        $paidSoFar = $livewire->getTableRecords()
                            ->filter(function ($row) use ($record) {
                                if ($row->paid_at < $record->paid_at) {
                                    return true;
                                }
                                if ($row->paid_at->eq($record->paid_at) && $row->created_at <= $record->created_at) {
                                    return true;
                                }
                                return false;
                            })
                            ->sum('amount');

                        return max(0, $totalPayable - (float) $paidSoFar);
                    }),
                TextColumn::make('bankAccount.account_name')
                    ->label('Bank'),
                TextColumn::make('payment_method')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('transaction.reference')
                    ->label('Ref')
                    ->placeholder('No Transaction'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record Repayment')
                    ->icon('heroicon-m-banknotes')
                    ->modalWidth('xl')
                    // Guard: only allow repayments on disbursed loans
                    ->visible(fn() => $this->ownerRecord->canRecordRepayment())
                    ->failureNotificationTitle('Failed to record repayment')
                    ->using(function (array $data): WidowLoanRepayment {
                        return app(WidowLoanService::class)->recordRepayment(
                            new RecordWidowLoanRepaymentData(
                                widowLoanId: $this->ownerRecord->id,
                                amount: (float)$data['amount'],
                                paidAt: $data['paid_at'],
                                bankAccountId: $data['bank_account_id'] ?? null,
                                paymentMethod: $data['payment_method'] ?? null,
                                notes: $data['notes'] ?? null,
                            )
                        );
                    })
                    ->after(function () {
                        Notification::make()
                            ->success()
                            ->title('Repayment Recorded')
                            ->body('The repayment has been recorded and the loan balance updated.')
                            ->send();
                    }),
            ])
            ->recordActions([
                Action::make('printReceipt')
                    ->label('Download PDF')
                    ->icon('heroicon-m-document-arrow-down')
                    ->color('success')
                    ->url(fn(WidowLoanRepayment $record) => route('repayments.receipt.download', $record))
                    ->openUrlInNewTab(),

                // ✅ NEW: Edit Action with chronological safeguard
                \Filament\Actions\EditAction::make()
                    ->disabled(fn(WidowLoanRepayment $record): bool =>
                        // Disable if ANY repayment exists on this loan that was paid AFTER this one
                    $record->widowLoan->repayments()
                        ->where(function ($q) use ($record) {
                            $q->where('paid_at', '>', $record->paid_at)
                                ->orWhere(function ($q2) use ($record) {
                                    // Also check created_at if multiple payments happen on the exact same day
                                    $q2->where('paid_at', $record->paid_at)
                                        ->where('created_at', '>', $record->created_at);
                                });
                        })
                        ->exists()
                    )
                    ->tooltip(function (\Filament\Actions\EditAction $action, WidowLoanRepayment $record) {
                        if ($record->widowLoan->repayments()
                            ->where(function ($q) use ($record) {
                                $q->where('paid_at', '>', $record->paid_at)
                                    ->orWhere(function ($q2) use ($record) {
                                        $q2->where('paid_at', $record->paid_at)
                                            ->where('created_at', '>', $record->created_at);
                                    });
                            })
                            ->exists()
                        ) {
                            return 'Cannot edit: A subsequent repayment has been recorded.';
                        }
                        return $action->getLabel();
                    }),
            ]);
    }
}
