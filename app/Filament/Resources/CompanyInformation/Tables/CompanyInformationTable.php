<?php

namespace App\Filament\Resources\CompanyInformation\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class CompanyInformationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn () => url('/images/default-logo.png')),

                TextColumn::make('company_name')
                    ->label('Foundation Name')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->label('Contact Email')
                    ->searchable()
                    ->icon('heroicon-m-envelope'),

                TextColumn::make('phone_no')
                    ->label('Phone')
                    ->searchable(),

                TextColumn::make('city')
                    ->searchable(),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('set_active')
                    ->label('Set Active')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->action(function ($record): void {
                        session(['active_business_id' => $record->business_id]);
                    }),
                ViewAction::make(),
                EditAction::make()
                    ->label('Manage'),
            ]);
    }
}
