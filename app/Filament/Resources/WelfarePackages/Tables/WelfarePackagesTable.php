<?php

namespace App\Filament\Resources\WelfarePackages\Tables;

use App\Enums\WelfarePackageStatus;
use App\Models\WelfarePackage;
use App\Services\WelfarePackageService;
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
                    ->color(fn(WelfarePackageStatus $state): string => $state->color())
                    ->icon(fn(WelfarePackageStatus $state): string => $state->icon()),

                TextColumn::make('start_date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->date('M d, Y')
                    ->sortable(),

                TextColumn::make('beneficiaries_count')
                    ->counts('beneficiaries')
                    ->label('Beneficiaries'),

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
                    ->query(fn(Builder $query) => $query->active())
                    ->toggle(),

                Filter::make('upcoming')
                    ->query(fn(Builder $query) => $query->upcoming())
                    ->toggle(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn(WelfarePackage $record): bool => $record->isDraft()),

                    Action::make('open')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Open Welfare Package')
                        ->modalDescription('Are you sure you want to open this package? It will be available for coordinators to suggest beneficiaries.')
                        ->visible(fn(WelfarePackage $record): bool => $record->canBeOpened())
                        ->action(function (WelfarePackage $record) {
                            app(WelfarePackageService::class)->openPackage($record);
                        }),

                    Action::make('close')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Close Welfare Package')
                        ->visible(fn(WelfarePackage $record): bool => $record->canBeClosed())
                        ->action(function (WelfarePackage $record) {
                            app(WelfarePackageService::class)->closePackage($record);
                        }),

                    Action::make('reopen')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->visible(fn(WelfarePackage $record): bool => $record->canBeReopened())
                        ->action(function (WelfarePackage $record) {
                            app(WelfarePackageService::class)->reopenPackage($record);
                        }),

                    Action::make('duplicate')
                        ->icon('heroicon-o-document-duplicate')
                        ->schema([
                            TextInput::make('new_name')
                                ->required()
                                ->default(fn(WelfarePackage $record) => $record->name . ' (Copy)'),
                            DatePicker::make('new_start_date')
                                ->required()
                                ->default(now()),
                            DatePicker::make('new_end_date')
                                ->required()
                                ->default(now()->addMonth()),
                        ])
                        ->action(function (WelfarePackage $record, array $data) {
                            app(WelfarePackageService::class)->duplicatePackage(
                                $record,
                                $data['new_name'],
                                Carbon::parse($data['new_start_date']),
                                Carbon::parse($data['new_end_date'])
                            );
                        }),

                    DeleteAction::make()
                        ->visible(fn(WelfarePackage $record): bool => $record->isDraft()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
