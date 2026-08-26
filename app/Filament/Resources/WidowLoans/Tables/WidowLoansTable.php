<?php

namespace App\Filament\Resources\WidowLoans\Tables;

use App\Enums\WidowLoanPerformanceStatus;
use App\Enums\WidowLoanRecoveryStatus;
use App\Enums\WidowLoanStatus;
use App\Models\WidowLoan;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->formatStateUsing(fn ($state, WidowLoan $record) => $state
                        ? "{$record->bankAccount->account_name} ({$record->bankAccount->account_number})"
                        : 'N/A')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('outstanding_balance')
                    ->label('Remaining Balance')
                    ->money('NGN')
                    ->state(fn (WidowLoan $record) => (float) $record->outstanding_balance)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Operational Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('performance_status')
                    ->label('Performance Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('days_past_due')
                    ->label('DPD')
                    ->state(fn (WidowLoan $record) => (int) $record->days_past_due)
                    ->formatStateUsing(fn ($state) => $state > 0 ? "{$state} d" : '0 d')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('overdue_amount')
                    ->label('Overdue')
                    ->money('NGN')
                    ->state(fn (WidowLoan $record) => (float) $record->overdue_amount)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('repayment_progress')
                    ->label('Repaid')
                    ->state(fn (WidowLoan $record) => $record->total_payable > 0
                        ? round(($record->total_paid / $record->total_payable) * 100).'%'
                        : '0%')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                IconColumn::make('hardship_active')
                    ->label('Hardship')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('recovery_status')
                    ->label('Recovery Status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('next_recovery_action_at')
                    ->label('Next Recovery Date')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('fully_repaid')
                    ->label('Cleared')
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('collector.name')
                    ->label('Marked Collected By')
                    ->placeholder('Not collected')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('collector_name')
                    ->label('Collector Name')
                    ->placeholder('Not collected')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Operational Status')
                    ->options(WidowLoanStatus::class),

                SelectFilter::make('performance_status')
                    ->label('Performance Status')
                    ->options(WidowLoanPerformanceStatus::class),

                TernaryFilter::make('needs_attention')
                    ->label('Needs Attention (Arrears/Delinquent)')
                    ->queries(
                        true: fn (Builder $query) => $query->whereIn('performance_status', [
                            WidowLoanPerformanceStatus::OVERDUE,
                            WidowLoanPerformanceStatus::DELINQUENT,
                            WidowLoanPerformanceStatus::DEFAULTED,
                        ]),
                        false: fn (Builder $query) => $query->whereNotIn('performance_status', [
                            WidowLoanPerformanceStatus::OVERDUE,
                            WidowLoanPerformanceStatus::DELINQUENT,
                            WidowLoanPerformanceStatus::DEFAULTED,
                        ]),
                    ),

                TernaryFilter::make('hardship_active')
                    ->label('Active Hardship'),

                SelectFilter::make('recovery_status')
                    ->label('Recovery Status')
                    ->options(WidowLoanRecoveryStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    // Generate schedule manually (only if APPROVED but schedule not yet created)
                    Action::make('generateSchedule')
                        ->label('Generate Schedule')
                        ->icon('heroicon-m-calendar-days')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn (WidowLoan $record) => $record->status === WidowLoanStatus::DISBURSED &&
                            $record->schedules()->count() === 0
                        )
                        ->action(fn (WidowLoan $record) => $record->generateLedger()),

                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn (WidowLoan $record) => \App\Filament\Resources\WidowLoans\WidowLoanResource::canEdit($record)),

                    Action::make('downloadStatement')
                        ->label('Download Statement')
                        ->icon('heroicon-m-document-text')
                        ->color('info')
                        ->url(fn ($record) => route('loans.statement.download', $record))
                        ->openUrlInNewTab()
                        ->visible(fn (WidowLoan $record) => $record->repayments()->exists()),

                    // Workflow actions in order
                    \App\Filament\Actions\ApproveWidowLoanAction::make(),
                    \App\Filament\Actions\RejectWidowLoanAction::make(),
                    \App\Filament\Actions\DisburseWidowLoanAction::make(),
                    \App\Filament\Actions\MarkLoanCollectedAction::make(),
                    \App\Filament\Actions\WriteOffWidowLoanAction::make(),
                ]),
            ]);
    }
}
