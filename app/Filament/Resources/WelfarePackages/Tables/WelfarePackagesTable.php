<?php

namespace App\Filament\Resources\WelfarePackages\Tables;

use App\Enums\WelfarePackageStatus;
use App\Models\WelfarePackage;
use App\Services\Welfare\WelfarePackageLifecycleService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WelfarePackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('font-bold'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (WelfarePackageStatus $state): string => $state->color())
                    ->icon(fn (WelfarePackageStatus $state): string => $state->icon()),

                TextColumn::make('start_date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('beneficiaries_count')
                    ->counts('beneficiaries')
                    ->label('Nominations'),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(WelfarePackageStatus::class),

                Filter::make('active')
                    ->query(fn (Builder $query) => $query->active())
                    ->toggle(),

                Filter::make('upcoming')
                    ->query(fn (Builder $query) => $query->upcoming())
                    ->toggle(),
            ])
            ->recordActions([
                ActionGroup::make([
                    // Always visible
                    ViewAction::make(),

                    // DRAFT only: Edit package definition/items
                    // OPEN + zero nominations: Edit still allowed
                    EditAction::make()
                        ->visible(fn (WelfarePackage $record): bool => $record->isCompositionEditable()),

                    // DRAFT → OPEN  (never shown for OPEN or CLOSED)
                    Action::make('open')
                        ->label('Open')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Open Welfare Package')
                        ->modalDescription('Opening this package will make it available for household nominations. You cannot undo this without closing it.')
                        ->visible(fn (WelfarePackage $record): bool => $record->canBeOpened())
                        ->action(function (WelfarePackage $record): void {
                            try {
                                app(WelfarePackageLifecycleService::class)->openPackage($record);
                                Notification::make()
                                    ->title('Package opened successfully.')
                                    ->success()
                                    ->send();
                            } catch (\RuntimeException $e) {
                                Notification::make()
                                    ->title('Cannot open package')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    // OPEN → CLOSED  (never shown for DRAFT or CLOSED)
                    Action::make('close')
                        ->label('Close')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Close Welfare Package')
                        ->modalDescription('Closing this package will stop new household nominations. Existing nominations remain intact.')
                        ->visible(fn (WelfarePackage $record): bool => $record->canBeClosed())
                        ->action(function (WelfarePackage $record): void {
                            try {
                                app(WelfarePackageLifecycleService::class)->closePackage($record);
                                Notification::make()
                                    ->title('Package closed.')
                                    ->success()
                                    ->send();
                            } catch (\RuntimeException $e) {
                                Notification::make()
                                    ->title('Cannot close package')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    // CLOSED → OPEN  (never shown for DRAFT or OPEN)
                    Action::make('reopen')
                        ->label('Reopen')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Reopen Welfare Package')
                        ->modalDescription('Reopening makes this package active for new nominations. Package composition remains locked if nominations already exist.')
                        ->visible(fn (WelfarePackage $record): bool => $record->canBeReopened())
                        ->action(function (WelfarePackage $record): void {
                            try {
                                app(WelfarePackageLifecycleService::class)->reopenPackage($record);
                                Notification::make()
                                    ->title('Package reopened.')
                                    ->success()
                                    ->send();
                            } catch (\RuntimeException $e) {
                                Notification::make()
                                    ->title('Cannot reopen package')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    // Always visible — duplicates always produce a DRAFT copy
                    Action::make('duplicate')
                        ->label('Duplicate')
                        ->icon('heroicon-o-document-duplicate')
                        ->schema([
                            TextInput::make('new_name')
                                ->required()
                                ->default(fn (WelfarePackage $record) => $record->name . ' (Copy)'),
                            DatePicker::make('new_start_date')
                                ->required()
                                ->default(now()),
                            DatePicker::make('new_end_date')
                                ->required()
                                ->default(now()->addMonth()),
                        ])
                        ->action(function (WelfarePackage $record, array $data): void {
                            app(\App\Services\WelfarePackageService::class)->duplicatePackage(
                                $record,
                                $data['new_name'],
                                Carbon::parse($data['new_start_date']),
                                Carbon::parse($data['new_end_date'])
                            );
                            Notification::make()
                                ->title('Package duplicated as draft.')
                                ->success()
                                ->send();
                        }),

                    // DRAFT only — safe to delete only when no nominations exist
                    DeleteAction::make()
                        ->visible(fn (WelfarePackage $record): bool =>
                            $record->isDraft() && ! $record->hasNominations()
                        ),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
