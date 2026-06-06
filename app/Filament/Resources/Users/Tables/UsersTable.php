<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('phone')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('designation')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),

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

                TextColumn::make('coordinatedZone.name')
                    ->label('Coordinated Zone')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->description(fn (User $record) => $record->isCoordinator() ? 'Official Coordinator' : null)
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->trueLabel('Active Users')
                    ->falseLabel('Inactive Users')
                    ->placeholder('All Users'),

                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->preload()
                    ->searchable(),

                SelectFilter::make('coordinatedZone')
                    ->label('Coordinated Zone')
                    ->relationship('coordinatedZone', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('gender')
                    ->options([
                        'male' => 'Male',
                        'female' => 'Female',
                        'other' => 'Other',
                    ]),
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
            ])
            ->defaultSort('created_at', 'desc');
    }
}
