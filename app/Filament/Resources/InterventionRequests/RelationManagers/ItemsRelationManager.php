<?php

namespace App\Filament\Resources\InterventionRequests\RelationManagers;

use App\Filament\Resources\InterventionRequests\InterventionRequestResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $relatedResource = InterventionRequestResource::class;

    protected static ?string $recordTitleAttribute = 'item_name';

    protected static ?string $title = 'Requested Items';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('item_name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. School Bag, Uniform, Food Basket'),

                    TextInput::make('orphan_class')
                        ->label('Size / Class Context')
                        ->placeholder('e.g. Large, Size 34, Grade 4'),
                ]),

                Grid::make(2)->schema([
                    TextInput::make('quantity_requested')
                        ->label('Qty Requested')
                        ->numeric()
                        ->default(1)
                        ->required(),

                    TextInput::make('quantity_fulfilled')
                        ->label('Qty Fulfilled')
                        ->numeric()
                        ->default(0)
                        ->helperText('This updates as interventions are recorded.'),
                ]),

                TextInput::make('specification')
                    ->label('Specific Details')
                    ->placeholder('e.g. Blue color, Waterproof material')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item_name')
            ->columns([
                TextColumn::make('item_name')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('orphan_class')
                    ->label('Context')
                    ->placeholder('N/A'),

                TextColumn::make('quantity_requested')
                    ->label('Requested')
                    ->alignCenter(),

                TextColumn::make('quantity_fulfilled')
                    ->label('Fulfilled')
                    ->alignCenter()
                    ->color(fn ($record) => $record->is_fully_fulfilled ? 'success' : 'warning'),

                IconColumn::make('is_fully_fulfilled')
                    ->label('Done')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Item')
                    ->icon('heroicon-m-plus')
                    ->modalWidth('2xl'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('2xl'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
