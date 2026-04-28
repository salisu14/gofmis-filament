<?php

namespace App\Filament\Resources\Widows\Tables;

use App\Filament\Resources\IdCards\IdCardResource;
use App\Filament\Resources\Widows\Actions\GenerateIdCardAction;
use App\Models\Widow;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class WidowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('picture_url')
                    ->label('Image')
                    ->circular()
                    ->disk('public'),

                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('reg_no')
                    ->label('Reg No')
                    ->searchable()
                    ->badge()
                    ->sortable(),

                TextColumn::make('nin')
                    ->label('NIN')
                    ->searchable(),

                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->searchable(),

                IconColumn::make('is_eligible')
                    ->label('Eligible')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('skills')
                    ->label('Skills')
                    ->badge()
                    ->separator(',')
                    ->limitList(2),

                TextColumn::make('deceased.full_name')
                    ->label('Deceased Head')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),

                    // ID Card Actions
                    GenerateIdCardAction::make(),

                    Action::make('view_card')
                        ->label('View ID Card')
                        ->icon('heroicon-o-eye')
                        ->color('info')
                        ->url(fn(Widow $record) => $record->idCards()->where('status', 'active')->first()
                            ? IdCardResource::getUrl('view', [
                                'record' => $record->idCards()->where('status', 'active')->first()
                            ])
                            : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn(Widow $record): bool => $record->idCards()->where('status', 'active')->exists()
                        ),

                    Action::make('print_card')
                        ->label('Print Card')
                        ->icon('heroicon-o-printer')
                        ->color('success')
                        ->url(fn(Widow $record) => ($card = $record->idCards()->where('status', 'active')->first())
                            ? route('id-cards.download', ['idCard' => $card])
                            : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn(Widow $record): bool => $record->idCards()->where('status', 'active')->exists()
                        ),

                    DeleteAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
