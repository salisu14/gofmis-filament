<?php

namespace App\Filament\Resources\ProjectExpenses\Schemas;

use App\Models\ProjectMilestone;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ProjectExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Expense Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('project_id')
                                    ->relationship('project', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->required(),

                                Select::make('milestone_id')
                                    ->relationship('milestone', 'title')
                                    ->searchable()
                                    ->preload()
                                    ->options(fn (Get $get): \Illuminate\Support\Collection =>
                                    ProjectMilestone::query()
                                        ->where('project_id', $get('project_id'))
                                        ->pluck('title', 'id')
                                    )
                                    ->nullable(),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('category')
                                    ->options([
                                        'materials' => 'Materials',
                                        'labor' => 'Labor/Wages',
                                        'transport' => 'Transport',
                                        'permits' => 'Permits/Fees',
                                        'equipment' => 'Equipment',
                                        'utilities' => 'Utilities',
                                        'other' => 'Other',
                                    ])
                                    ->native(false)
                                    ->required(),

                                TextInput::make('amount')
                                    ->numeric()
                                    ->prefix('₦')
                                    ->required(),

                                DatePicker::make('expense_date')
                                    ->default(now())
                                    ->native(false)
                                    ->required(),
                            ]),

                        TextInput::make('description')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('receipt_number')
                            ->nullable(),
                    ]),

                Section::make('Attachments & Notes')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                FileUpload::make('receipt_path')
                                    ->label('Receipt Upload')
                                    ->directory('project-receipts')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->maxSize(5120)
                                    ->downloadable()
                                    ->openable(),

                                Textarea::make('notes')
                                    ->rows(3)
                                    ->nullable(),
                            ]),
                    ]),
            ]);
    }
}
