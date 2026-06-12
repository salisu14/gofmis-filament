<?php

namespace App\Filament\Resources\EducationFeeInvoices\Tables;

use App\Models\BankAccount;
use App\Models\EducationFeeInvoice;
use App\Services\EducationFeeInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EducationFeeInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('education.orphan.full_name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('reference')
                    ->label('Invoice Ref')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('period')
                    ->label('Period')
                    ->searchable(),

                TextColumn::make('amount')
                    ->label('Invoiced')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('payments_sum_amount')
                    ->label('Paid')
                    ->money('NGN')
                    ->sum('payments', 'amount')
                    ->color('success'),

                TextColumn::make('balance')
                    ->label('Balance')
                    ->money('NGN')
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'pending' => 'danger',
                        'cancelled', 'void' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('voided_at')
                    ->label('Voided')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('void_reason')
                    ->label('Void Reason')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'partial' => 'Partial',
                        'paid' => 'Paid',
                        'void' => 'Void',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('payBalance')
                        ->label('Pay Balance')
                        ->icon('heroicon-m-banknotes')
                        ->color('success')
                        ->modalHeading(fn(EducationFeeInvoice $record): string => 'Pay outstanding balance for ' . $record->reference)
                        ->modalDescription(fn(EducationFeeInvoice $record): string => 'Outstanding balance: ₦' . number_format($record->balance, 2))
                        ->visible(fn(EducationFeeInvoice $record): bool => !$record->isFinalized() && $record->balance > 0)
                        ->schema(fn(EducationFeeInvoice $record): array => [
                            Select::make('bank_account_id')
                                ->label('Paying Account')
                                ->options(fn(): array => self::bankAccountOptions($record))
                                ->searchable()
                                ->preload()
                                ->required()
                                ->helperText($record->education?->orphan?->hasActiveSponsorship()
                                    ? 'This orphan has active sponsorship. Prefer a benevolent/sponsor education account when available.'
                                    : 'The selected education account will be debited.'),
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
                                ->default('transfer')
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (EducationFeeInvoice $record, array $data): void {
                            try {
                                app(EducationFeeInvoiceService::class)->payOutstandingBalance($record, $data);

                                Notification::make()
                                    ->title('Invoice fully paid')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Payment failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('refreshStatus')
                        ->label('Refresh Status')
                        ->icon('heroicon-m-arrow-path')
                        ->color('gray')
                        ->visible(fn(EducationFeeInvoice $record): bool => !$record->isVoided())
                        ->action(function (EducationFeeInvoice $record): void {
                            app(EducationFeeInvoiceService::class)->refreshStatus($record);

                            Notification::make()
                                ->title('Invoice status refreshed')
                                ->success()
                                ->send();
                        }),

                    Action::make('void')
                        ->label('Void')
                        ->icon('heroicon-m-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading(fn(EducationFeeInvoice $record): string => 'Void invoice ' . $record->reference)
                        ->modalDescription('Voiding reverses recorded education fee payments back to their paying accounts and locks the invoice.')
                        ->visible(fn(EducationFeeInvoice $record): bool => !$record->isVoided())
                        ->schema([
                            Textarea::make('reason')
                                ->label('Void reason')
                                ->required()
                                ->rows(3)
                                ->maxLength(1000),
                        ])
                        ->action(function (EducationFeeInvoice $record, array $data): void {
                            try {
                                app(EducationFeeInvoiceService::class)->void($record, $data['reason']);

                                Notification::make()
                                    ->title('Invoice voided')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Void failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    EditAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    private static function bankAccountOptions(EducationFeeInvoice $invoice): array
    {
        $sponsored = (bool)$invoice->education?->orphan?->hasActiveSponsorship();

        return BankAccount::query()
            ->dedicatedTo(EducationFeeInvoiceService::PAYING_ACCOUNT_USAGES)
            ->orderByRaw('case when usage = ? then 0 else 1 end', [
                $sponsored ? BankAccount::USAGE_EDUCATION_BENEVOLENT : BankAccount::USAGE_EDUCATION,
            ])
            ->orderBy('account_name')
            ->get()
            ->mapWithKeys(fn(BankAccount $account): array => [
                $account->id => "{$account->account_name} ({$account->usage_label}) - ₦" . number_format((float)$account->ledger_balance, 2),
            ])
            ->all();
    }
}
