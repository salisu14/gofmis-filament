<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    protected static ?string $relatedResource = ProjectResource::class;

    protected static ?int $sort = 2;

    protected static ?string $label = 'Milestone';

    protected static ?string $title = 'Project Milestones';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->rows(2),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'blocked' => 'Blocked',
                    ])
                    ->default('pending')
                    ->required(),
                TextInput::make('budget_allocated')
                    ->numeric()
                    ->prefix('₦'),
                DatePicker::make('due_date'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->weight('bold'),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'pending',
                        'warning' => 'in_progress',
                        'success' => 'completed',
                        'danger' => 'blocked',
                    ]),
                TextColumn::make('budget_allocated')
                    ->money('NGN'),
                TextColumn::make('due_date')
                    ->date('M d, Y'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Milestone'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
