<?php

namespace App\Filament\Resources\WidowLoanRepayments\Tables;

use App\Models\WidowLoanRepayment;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WidowLoanRepaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('receipt_number')
                    ->label('Receipt #')
                    ->searchable()
                    ->copyable()
                    ->formatStateUsing(fn($state) => 'RCP-' . str_pad($state, 5, '0', STR_PAD_LEFT))
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('widowLoan.widow.full_name')
                    ->label('Beneficiary')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('widowLoan.purpose')
                    ->label('Loan Purpose')
                    ->searchable()
                    ->limit(25)
                    ->toggleable(),

                TextColumn::make('paid_at')
                    ->label('Date Paid')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Amount Paid')
                    ->money('NGN')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),

                TextColumn::make('balance_after')
                    ->label('Balance After')
                    ->money('NGN')
                    ->alignEnd()
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->label('Method')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('bankAccount.account_name')
                    ->label('Bank Account')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('widow_loan_id')
                    ->label('Widow Loan')
                    ->relationship('widowLoan', 'purpose')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Cash',
                        'transfer' => 'Bank Transfer',
                        'deduction' => 'Monthly Deduction',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('printReceipt')
                        ->label('Download PDF')
                        ->icon('heroicon-m-document-arrow-down')
                        ->color('success')
                        ->url(fn(WidowLoanRepayment $record) => route('repayments.receipt.download', $record))
                        ->openUrlInNewTab(),

                    // ✅ NEW: Edit Action with chronological safeguard
                    \Filament\Actions\EditAction::make()
                        ->disabled(fn(WidowLoanRepayment $record): bool => // Disable if ANY repayment exists on this loan that was paid AFTER this one
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
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                ]),
            ])
            ->defaultSort('paid_at', 'desc');
    }
}
