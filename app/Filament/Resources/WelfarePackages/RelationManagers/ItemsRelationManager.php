<?php

namespace App\Filament\Resources\WelfarePackages\RelationManagers;

use App\Filament\Resources\WelfarePackages\WelfarePackageResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $relatedResource = WelfarePackageResource::class;

    protected static ?string $title = 'Package Items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('item_id')
                    ->relationship('item', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

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

                TextColumn::make('category.name')
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
                    ->visible(fn () => $this->getOwnerRecord()->isDraft()),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isDraft()),
                DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isDraft()),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->isDraft()),
            ]);
    }
}
