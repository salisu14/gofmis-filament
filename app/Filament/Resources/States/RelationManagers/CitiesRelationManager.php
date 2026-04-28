<?php

namespace App\Filament\Resources\States\RelationManagers;

use App\Filament\Resources\States\StateResource;
use App\Models\City;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'cities';

    protected static ?string $relatedResource = StateResource::class;

    protected static ?string $navigationIcon = 'heroicon-s-city';

    protected static ?string $navigationGroup = 'Relations';

    protected static ?string $title = 'Cities & Towns';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                // Show a count of towns to give feedback to the user
                TextColumn::make('towns_count')
                    ->counts('towns')
                    ->label('Towns')
                    ->badge()
                    ->color('gray'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-m-plus')
                    ->label('Add City'),
            ])
            ->recordActions([
                // THE NESTED TOWN MANAGEMENT ACTION
                Action::make('manageTowns')
                    ->label('Manage Towns')
                    ->icon('heroicon-m-map-pin')
                    ->color('info')
                    ->modalHeading(fn(City $record) => "Towns in {$record->name}")
                    ->modalDescription('Add or remove towns belonging to this city.')
                    ->modalSubmitActionLabel('Save Changes')
                    ->fillForm(fn(City $record): array => [
                        'towns' => $record->towns->toArray(),
                    ])
                    ->schema([
                        Repeater::make('towns')
                            ->relationship('towns') // Uses the HasMany relationship in City model
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->placeholder('Enter town name'),
                            ])
                            ->grid(2)
                            ->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
                            ->defaultItems(0)
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->addActionLabel('Add New Town'),
                    ])
                    ->action(function (City $record, array $data): void {
                        // The relationship() call in the repeater handles saving automatically,
                        // but you can perform additional logic here if needed.
                        $record->touch(); // Refresh timestamps
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
