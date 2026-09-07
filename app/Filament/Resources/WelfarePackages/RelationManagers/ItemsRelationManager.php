<?php

namespace App\Filament\Resources\WelfarePackages\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Package Items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            $item = \App\Models\Item::with('category')->find($state);
                            $set('category_display', $item?->category?->name ?? '-');
                        } else {
                            $set('category_display', null);
                        }
                    }),

                TextInput::make('category_display')
                    ->label('Category')
                    ->helperText('Derived automatically from selected item')
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(function (Get $get, $record) {
                        $itemId = $get('item_id');
                        if ($itemId) {
                            $item = \App\Models\Item::with('category')->find($itemId);

                            return $item?->category?->name;
                        }

                        return $record?->item?->category?->name;
                    }),

                TextInput::make('quantity_per_family')
                    ->numeric()
                    ->minValue(1)
                    ->default(1)
                    ->required(),

                Textarea::make('notes')
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item.name')
            ->columns([
                TextColumn::make('item.name')
                    ->searchable()
                    ->sortable()
                    ->weight('font-bold'),

                TextColumn::make('item.category.name')
                    ->label('Category')
                    ->badge()
                    ->color('info'),

                TextColumn::make('quantity_per_family')
                    ->numeric()
                    ->suffix(' per family'),

                TextColumn::make('notes')
                    ->limit(50)
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isCompositionEditable()),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isCompositionEditable()),
                DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isCompositionEditable()),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isCompositionEditable()),
            ]);
    }
}
