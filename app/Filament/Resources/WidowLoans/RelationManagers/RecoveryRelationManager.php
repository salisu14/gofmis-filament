<?php

namespace App\Filament\Resources\WidowLoans\RelationManagers;

use App\Enums\WidowLoanPromiseStatus;
use App\Models\WidowLoanRecoveryCase;
use App\Services\WidowLoanRecoveryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecoveryRelationManager extends RelationManager
{
    protected static ?string $model = WidowLoanRecoveryCase::class;

    protected static string $relationship = 'recoveryCases';

    protected static ?string $title = 'Recovery Cases & Activity History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('opened_at')
                    ->label('Opened On')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('priority')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('opener.name')
                    ->label('Opened By')
                    ->placeholder('System'),

                TextColumn::make('current_action')
                    ->label('Last Recorded Action')
                    ->placeholder('No activity logged'),

                TextColumn::make('next_action_at')
                    ->label('Next Follow-up Due')
                    ->dateTime()
                    ->placeholder('None scheduled'),

                TextColumn::make('active_promises_count')
                    ->label('Active Promises')
                    ->state(fn (WidowLoanRecoveryCase $record) => $record->promises()->where('status', WidowLoanPromiseStatus::OPEN)->count())
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'primary' : 'gray'),

                TextColumn::make('activities_summary')
                    ->label('Activities Count')
                    ->state(fn (WidowLoanRecoveryCase $record) => $record->activities()->count().' recorded')
                    ->color('gray'),
            ])
            ->defaultSort('opened_at', 'desc')
            ->recordActions([
                Action::make('fulfillPromise')
                    ->label('Fulfill Promise')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (WidowLoanRecoveryCase $record) => $record->promises()->where('status', WidowLoanPromiseStatus::OPEN)->exists())
                    ->action(function (WidowLoanRecoveryCase $record) {
                        $promise = $record->promises()->where('status', WidowLoanPromiseStatus::OPEN)->first();
                        if (! $promise) {
                            return;
                        }

                        try {
                            app(WidowLoanRecoveryService::class)->fulfillPromise($promise->id);

                            Notification::make()
                                ->success()
                                ->title('Promise Fulfilled')
                                ->body('The promise to pay has been marked as fulfilled and delinquency re-evaluated.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Action Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),

                Action::make('breakPromise')
                    ->label('Mark Promise Broken')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (WidowLoanRecoveryCase $record) => $record->promises()->where('status', WidowLoanPromiseStatus::OPEN)->exists())
                    ->action(function (WidowLoanRecoveryCase $record) {
                        $promise = $record->promises()->where('status', WidowLoanPromiseStatus::OPEN)->first();
                        if (! $promise) {
                            return;
                        }

                        try {
                            app(WidowLoanRecoveryService::class)->breakPromise($promise->id);

                            Notification::make()
                                ->warning()
                                ->title('Promise Broken')
                                ->body('The promise to pay was marked broken and the recovery case escalated.')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Action Failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ]);
    }
}
