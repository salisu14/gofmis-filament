<?php

namespace App\Filament\Resources\InterventionRequests\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InterventionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interventions';

    protected static ?string $recordTitleAttribute = 'date_given';

    protected static ?string $title = 'Fulfillment History (Interventions)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('collected_by')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Name of person who received items'),

                DatePicker::make('date_given')
                    ->default(now())
                    ->required(),

                FileUpload::make('document_url')
                    ->label('Proof of Delivery/Photo')
                    ->directory('interventions')
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->placeholder('Specific items given or comments...')
                    ->columnSpanFull(),

                // Automatically link the orphan from the parent request
                Hidden::make('orphan_id')
                    ->default(fn(RelationManager $livewire) => $livewire->getOwnerRecord()->orphan_id),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date_given')
                    ->date()
                    ->sortable(),

                TextColumn::make('collected_by')
                    ->searchable(),

                TextColumn::make('notes')
                    ->limit(50)
                    ->placeholder('No notes'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Record New Delivery')
                    ->icon('heroicon-m-truck'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
