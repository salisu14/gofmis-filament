<?php

namespace App\Filament\Resources\EducationFeeInvoices\RelationManagers;

use App\Models\BankAccount;
use App\Models\EducationFeePayment;
use App\Filament\Resources\EducationFeeInvoices\EducationFeeInvoiceResource;
use App\Services\EducationFeeInvoiceService;
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
                            ->options(fn (): array => $this->bankAccountOptions())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->helperText(fn (): string => $this->getOwnerRecord()->education?->orphan?->hasActiveSponsorship()
                                ? 'This orphan has active sponsorship. Prefer a benevolent/sponsor education account when available.'
                                : 'The selected education account is debited when this payment is recorded.'),

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
                    ->visible(fn () => ! $this->getOwnerRecord()->isFinalized())
                    ->using(function (array $data): EducationFeePayment {
                        return app(EducationFeeInvoiceService::class)
                            ->recordPayment($this->getOwnerRecord(), $data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (EducationFeePayment $record) => ! $record->transaction()->exists()),
                DeleteAction::make()
                    ->visible(fn (EducationFeePayment $record) => ! $record->transaction()->exists()),
            ]);
    }

    private function bankAccountOptions(): array
    {
        $orphan = $this->getOwnerRecord()->education?->orphan;
        $sponsored = (bool) $orphan?->hasActiveSponsorship();

        return BankAccount::query()
            ->dedicatedTo(EducationFeeInvoiceService::PAYING_ACCOUNT_USAGES)
            ->orderByRaw('case when usage = ? then 0 else 1 end', [
                $sponsored ? BankAccount::USAGE_EDUCATION_BENEVOLENT : BankAccount::USAGE_EDUCATION,
            ])
            ->orderBy('account_name')
            ->get()
            ->mapWithKeys(fn (BankAccount $account): array => [
                $account->id => "{$account->account_name} ({$account->usage_label}) - ₦".number_format((float) $account->ledger_balance, 2),
            ])
            ->all();
    }
}
