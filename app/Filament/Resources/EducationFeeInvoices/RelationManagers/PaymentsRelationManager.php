<?php

namespace App\Filament\Resources\EducationFeeInvoices\RelationManagers;

use App\Models\BankAccount;
use App\Models\EducationFeePayment;
use App\Services\EducationFeeInvoiceService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static ?string $title = 'Payment History';

    public function form(Schema $schema): Schema
    {
        $invoice = $this->getOwnerRecord();
        $orphan = $invoice->education?->orphan;
        $institution = $invoice->education?->institution;

        return $schema
            ->schema([
                Section::make('Invoice Summary')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)->schema([
                            Placeholder::make('summary_reference')
                                ->label('Invoice Reference')
                                ->content($invoice->invoice_number ?? 'N/A'),

                            Placeholder::make('summary_amount')
                                ->label('Total Invoice Amount')
                                ->content('₦'.number_format((float) $invoice->amount, 2)),

                            Placeholder::make('summary_paid')
                                ->label('Amount Already Paid')
                                ->content('₦'.number_format((float) $invoice->paid_amount, 2)),

                            Placeholder::make('summary_balance')
                                ->label('Outstanding Balance')
                                ->content('₦'.number_format(max(0, (float) $invoice->balance), 2)),

                            Placeholder::make('summary_student')
                                ->label('Student / Beneficiary')
                                ->content($orphan?->display_name ?? 'N/A'),

                            Placeholder::make('summary_institution')
                                ->label('Institution')
                                ->content($institution?->name ?? 'N/A'),
                        ]),
                    ]),

                Section::make('Payment Details')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Select::make('bank_account_id')
                            ->label('Paying Bank Account')
                            ->options(fn (): array => $this->bankAccountOptions())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit')
                            ->columnSpanFull()
                            ->helperText(fn (): string => $this->getOwnerRecord()->education?->orphan?->hasActiveSponsorship()
                                ? 'This student has an active sponsorship. Use the sponsor/benevolent education account where appropriate.'
                                : 'The selected education account will be debited when this payment is recorded.'),

                        Grid::make(2)->schema([
                            TextInput::make('amount')
                                ->label('Payment Amount')
                                ->numeric()
                                ->prefix('₦')
                                ->required()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->disabledOn('edit')
                                ->helperText(fn () => 'Outstanding balance: ₦'.number_format(max(0, (float) $this->getOwnerRecord()->balance), 2)),

                            DatePicker::make('payment_date')
                                ->label('Payment Date')
                                ->default(now())
                                ->required()
                                ->native(false),
                        ]),

                        Grid::make(2)->schema([
                            Select::make('payment_method')
                                ->label('Payment Method')
                                ->options([
                                    'cash' => 'Cash',
                                    'bank_deposit' => 'Bank Deposit',
                                    'transfer' => 'Bank Transfer',
                                    'pos' => 'POS',
                                ])
                                ->required()
                                ->native(false),

                            TextInput::make('reference')
                                ->label('Payment Reference')
                                ->placeholder('Generated automatically')
                                ->disabled()
                                ->dehydrated(false)
                                ->visibleOn('edit'),
                        ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Payment Date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->money('NGN')
                    ->weight('bold')
                    ->summarize(Sum::make()->money('NGN')->label('Total Paid')),

                TextColumn::make('bankAccount.account_name')
                    ->label('Source Account')
                    ->description(fn (EducationFeePayment $record) => $record->bankAccount?->usage_label)
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->label('Payment Method')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'Cash',
                        'bank_deposit' => 'Bank Deposit',
                        'transfer' => 'Bank Transfer',
                        'pos' => 'POS',
                        default => str($state)->headline()->toString(),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'info',
                        'bank_deposit' => 'warning',
                        'transfer' => 'success',
                        'pos' => 'primary',
                        default => 'gray',
                    }),

                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono')
                    ->placeholder('—'),
            ])
            ->defaultSort('payment_date', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Record Payment')
                    ->icon('heroicon-o-banknotes')
                    ->modalHeading('Record Education Fee Payment')
                    ->modalDescription('Record a payment against this invoice. The selected education bank account will be debited after validation.')
                    ->modalWidth('3xl')
                    ->visible(fn () => ! $this->getOwnerRecord()->isFinalized())
                    ->successNotificationTitle('Payment recorded successfully')
                    ->using(function (array $data): EducationFeePayment {
                        return app(EducationFeeInvoiceService::class)
                            ->recordPayment($this->getOwnerRecord(), $data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->icon('heroicon-o-pencil-square')
                    ->tooltip('Edit payment')
                    ->visible(fn (EducationFeePayment $record) => ! $record->transaction()->exists()),
                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->tooltip('Delete payment')
                    ->requiresConfirmation()
                    ->modalHeading('Delete Payment')
                    ->modalDescription('Are you sure you want to delete this payment record? This action cannot be undone.')
                    ->visible(fn (EducationFeePayment $record) => ! $record->transaction()->exists()),
            ])
            ->emptyStateHeading('No payments recorded')
            ->emptyStateDescription('Record the first payment for this education fee invoice.')
            ->emptyStateIcon('heroicon-o-banknotes');
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
                $account->id => "{$account->account_name} — {$account->usage_label} — ₦".number_format((float) $account->ledger_balance, 2),
            ])
            ->all();
    }
}
