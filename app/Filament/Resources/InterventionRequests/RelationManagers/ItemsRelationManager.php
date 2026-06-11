<?php

namespace App\Filament\Resources\InterventionRequests\RelationManagers;

use App\Filament\Resources\InterventionRequests\InterventionRequestResource;
use App\Models\Category;
use App\Models\Item;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
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
                Select::make('item_id')
                    ->label('Item')
                    ->searchable()
                    ->preload()
                    ->required()
                    // Build the options array grouped by Category natively
                    ->options(
                        Item::with('category')->get()
                            ->groupBy(fn ($item) => $item->category?->name ?? 'Uncategorized')
                            ->map(fn ($items) => $items->pluck('name', 'id'))
                    )
                    // Allow creating a new master Item on the fly
                    ->createOptionForm([
                        TextInput::make('name')->required()->maxLength(255),
                        Select::make('category_id')
                            ->label('Category')
                            // ✅ FIX: Use direct options instead of relationship() to avoid model context crash
                            ->options(Category::pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        TextInput::make('description')->maxLength(255),
                    ])
                    ->createOptionModalHeading('Create New Master Item')
                    // Auto-fill specification and snapshot the name
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $item = Item::find($state);
                            $set('item_name', $item?->name); // Snapshot the name
                            $set('specification', $item?->description);
                        }
                    }),

                Hidden::make('item_name'), // Keeps the snapshot but hides it from the UI

                Grid::make(2)->schema([
                    TextInput::make('orphan_class')
                        ->label('Size / Class Context')
                        ->placeholder('e.g. Large, Size 34, Grade 4'),

                    TextInput::make('specification')
                        ->label('Specific Details')
                        ->placeholder('e.g. Blue color, Waterproof material'),
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
                        ->disabled() // System updates this, not users
                        ->dehydrated(false),
                ]),
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

                TextColumn::make('item.category.name')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('orphan_class')
                    ->label('Context')
                    ->placeholder('N/A')
                    ->toggleable(),

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
