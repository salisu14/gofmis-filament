<?php

namespace App\Filament\Imprest\Widgets;

use App\Filament\Imprest\Resources\ImprestTransactionResource;
use App\Models\ImprestTransaction;
use Filament\Actions\Action;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingTransactionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Pending Approvals';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ImprestTransaction::query()
                    ->pending()
                    ->with(['fund', 'custodian'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('voucher_no')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Deceased')
                    ->searchable(),

                Tables\Columns\TextColumn::make('item_service')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_price')
                    ->money('NGN')
                    ->sortable()
                    ->alignment('right'),

                Tables\Columns\TextColumn::make('custodian.name')
                    ->label('Custodian'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Transaction')
                    ->modalDescription('Are you sure you want to approve this transaction?')
                    ->modalSubmitActionLabel('Yes, Approve')
                    ->visible(fn(ImprestTransaction $record): bool => auth()->user()->can('approve', $record))
                    ->action(function (ImprestTransaction $record) {
                        $service = app(\App\Services\Contracts\Imprest\ImprestTransactionServiceInterface::class);
                        $service->approve(new \App\Data\Imprest\ApproveTransactionDto(
                            transactionId: $record->id,
                            approvedBy: auth()->id(),
                        ));
                        $this->dispatch('refresh');
                    }),

                Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->url(fn(ImprestTransaction $record): string => ImprestTransactionResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('No pending transactions')
            ->emptyStateDescription('All transactions have been processed.')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
