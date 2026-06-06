<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('coordinatedZone.name')
                    ->label('Coordinated Zone')
                    ->placeholder('No zone assigned')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->description(fn (User $record) => $record->hasRole('coordinator') ? 'Official Coordinator' : null)
                    ->toggleable(),

                TextColumn::make('roles.name')
                    ->label('Roles / Access')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'coordinator' => 'success',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('coordinatedZone')
                    ->label('Filter by Zone')
                    ->relationship('coordinatedZone', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->preload(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ])->tooltip('Actions'),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(function (): bool {
                            $user = auth()->user();

                            return $user?->can('delete_users') || $user?->can('user_delete');
                        }),
                ]),
            ]);
    }
}
