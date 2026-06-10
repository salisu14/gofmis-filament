<?php

namespace App\Filament\Resources\WidowLoans\Tables;

use App\Enums\WidowLoanStatus;
use App\Models\WidowLoan;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WidowLoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('widow.full_name')
                    ->label('Widow')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('widow.deceased.zone.name')
                    ->label('Zone')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('principal_amount')
                    ->label('Principal')
                    ->money('NGN')
                    ->sortable(),

                TextColumn::make('bankAccount.account_name')
                    ->label('Bank Account')
                    ->formatStateUsing(fn($state, WidowLoan $record) => $state
                        ? "{$record->bankAccount->account_name} ({$record->bankAccount->account_number})"
                        : 'N/A'),

                TextColumn::make('outstanding_balance')
                    ->label('Remaining Balance')
                    ->money('NGN')
                    ->state(fn(WidowLoan $record) => (float)$record->total_payable - (float)$record->total_paid)
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                    ->weight('bold'),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('repayment_progress')
                    ->label('Repaid')
                    ->state(fn(WidowLoan $record) => $record->total_payable > 0
                        ? round(($record->total_paid / $record->total_payable) * 100) . '%'
                        : '0%')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('fully_repaid')
                    ->label('Cleared')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('collector.name')
                    ->label('Collected By')
                    ->placeholder('Not collected')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(WidowLoanStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    // Generate schedule manually (only if APPROVED but schedule not yet created)
                    Action::make('generateSchedule')
                        ->label('Generate Schedule')
                        ->icon('heroicon-m-calendar-days')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn(WidowLoan $record) => $record->status === WidowLoanStatus::DISBURSED &&
                            $record->schedules()->count() === 0
                        )
                        ->action(fn(WidowLoan $record) => $record->generateLedger()),

                    ViewAction::make(),
                    EditAction::make(),

                    Action::make('downloadStatement')
                        ->label('Download Statement')
                        ->icon('heroicon-m-document-text')
                        ->color('info')
                        ->url(fn($record) => route('loans.statement.download', $record))
                        ->openUrlInNewTab()
                        ->visible(fn($record) => $record->status !== \App\Enums\WidowLoanStatus::DRAFT),

                    // Workflow actions in order
                    \App\Filament\Actions\ApproveWidowLoanAction::make(),
                    \App\Filament\Actions\RejectWidowLoanAction::make(),
                    \App\Filament\Actions\DisburseWidowLoanAction::make(),
                    \App\Filament\Actions\MarkLoanCollectedAction::make(),
                ])
            ]);
    }
}
