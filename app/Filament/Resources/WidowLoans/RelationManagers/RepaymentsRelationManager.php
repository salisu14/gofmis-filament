<?php

namespace App\Filament\Resources\WidowLoans\RelationManagers;

/* -----------------------------
 | 2. LOAN REPAYMENTS MANAGER
 ------------------------------*/

use App\Data\Loan\RecordWidowLoanRepaymentData;
use App\Enums\WidowLoanStatus;
use App\Services\WidowLoanService;
use App\Models\WidowLoan;
use App\Models\WidowLoanRepayment;
use App\Models\BankAccount;
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
                    ->numeric()
                    ->prefix('₦')
                    ->required(),
                DatePicker::make('paid_at')
                    ->default(now())
                    ->required()
                    ->native(false),
                Select::make('bank_account_id')
                    ->label('Receiving Bank Account')
                    ->options(
                        BankAccount::query()
                            ->orderBy('account_name')
                            ->get()
                            ->mapWithKeys(fn (BankAccount $bank) => [
                                $bank->id => "{$bank->account_name} ({$bank->account_number})",
                            ])
                            ->toArray()
                    )
                    ->default(fn () => $this->ownerRecord?->bank_account_id)
                    ->searchable()
                    ->required(),
                Select::make('payment_method')
                    ->options([
                        'cash'      => 'Cash',
                        'transfer'  => 'Bank Transfer',
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
                TextColumn::make('paid_at')->date()->sortable(),
                TextColumn::make('amount')->money('NGN')->summarize(Sum::make()->money('NGN')),
                TextColumn::make('bankAccount.account_name')->label('Bank'),
                TextColumn::make('payment_method')->badge()->color('gray'),
                TextColumn::make('transaction.reference')->label('Ref')->placeholder('No Transaction'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record Repayment')
                    ->icon('heroicon-m-banknotes')
                    ->modalWidth('xl')
                    // Guard: only allow repayments on disbursed loans
                    ->visible(fn () => $this->ownerRecord->canRecordRepayment())
                    ->failureNotificationTitle('Failed to record repayment')
                    ->using(function (array $data): WidowLoanRepayment {
                        return app(WidowLoanService::class)->recordRepayment(
                            new RecordWidowLoanRepaymentData(
                                widowLoanId:   $this->ownerRecord->id,
                                amount:        (float) $data['amount'],
                                paidAt:        $data['paid_at'],
                                bankAccountId: $data['bank_account_id'] ?? null,
                                paymentMethod: $data['payment_method'] ?? null,
                                notes:         $data['notes'] ?? null,
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
                    ->label('Receipt')
                    ->icon('heroicon-m-printer')
                    ->color('info')
                    ->modalHeading('Repayment Receipt')
                    ->modalContent(fn (WidowLoanRepayment $record) => view('components.loan-receipt', [
                        'record' => $record,
                        'widow'  => $record->widowLoan->widow,
                        'balance' => max(
                            0,
                            (float) $record->widowLoan->total_payable
                            - (float) $record->widowLoan->repayments()
                                ->where('paid_at', '<=', $record->paid_at)
                                ->sum('amount')
                        ),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ]);
    }
}
