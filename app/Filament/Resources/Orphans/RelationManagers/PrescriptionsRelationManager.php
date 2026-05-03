<?php

namespace App\Filament\Resources\Orphans\RelationManagers;

use App\Enums\IllnessCategory;
use App\Filament\Resources\Orphans\OrphanResource;
use App\Models\Illness;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrescriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'prescriptions';
    protected static ?string $relatedResource = OrphanResource::class;

    protected static ?string $recordTitleAttribute = 'illness';

    protected static ?string $title = 'Medical History & Prescriptions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Clinical Details')
                    ->description('Diagnosis and physician information.')
                    ->icon('heroicon-m-beaker')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('doctor_name')
                                ->label('Attending Doctor')
                                ->required(),

                            Select::make('illness_id')
                                ->label('Illness / Diagnosis')
                                ->relationship('illnessModel', 'name')
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->optionsLimit(50)
                                ->getOptionLabelFromRecordUsing(fn(Illness $record): string => "{$record->name} — {$record->category?->label()}")
                                ->createOptionForm([
                                    Section::make('New Illness')
                                        ->schema([
                                            TextInput::make('name')
                                                ->label('Illness Name')
                                                ->required()
                                                ->unique(Illness::class, 'name')
                                                ->maxLength(255)
                                                ->placeholder('e.g. Sickle Cell Anemia'),

                                            Select::make('category')
                                                ->label('Category')
                                                ->options(IllnessCategory::class)
                                                ->enum(IllnessCategory::class)
                                                ->required()
                                                ->native(false),

                                            Textarea::make('description')
                                                ->label('Description / Symptoms')
                                                ->rows(2)
                                                ->placeholder('Brief description or common symptoms...'),
                                        ]),
                                ])
                                ->createOptionUsing(function (array $data): string {
                                    return Illness::create($data)->getKey();
                                })
                                ->editOptionForm([
                                    Section::make('Edit Illness')
                                        ->schema([
                                            TextInput::make('name')
                                                ->required()
                                                ->maxLength(255),

                                            Select::make('category')
                                                ->options(IllnessCategory::class)
                                                ->enum(IllnessCategory::class)
                                                ->required()
                                                ->native(false),

                                            Textarea::make('description')
                                                ->rows(2),
                                        ]),
                                ])
                                ->columnSpan(2) // Makes illness select wider (2/3 of row)
                                ->required(),

                            DatePicker::make('prescription_date')
                                ->label('Visit Date')
                                ->default(now())
                                ->required()
                                ->native(false),
                        ]),
                    ]),

                Section::make('Pharmacy & Costs')
                    ->description('Prescribed medications and associated billing.')
                    ->schema([
                        Select::make('medications')
                            ->multiple()
                            ->relationship('medications', 'name')
                            ->preload()
                            ->searchable()
                            ->hint('Select from pharmacy list.')
                            ->columnSpanFull(),

                        Grid::make(2)->schema([
                            TextInput::make('lab_test_cost')
                                ->numeric()
                                ->prefix('₦')
                                ->default(0),
                            TextInput::make('drug_cost')
                                ->numeric()
                                ->prefix('₦')
                                ->default(0),
                        ]),

                        Textarea::make('note')
                            ->label('Dosage / Clinical Notes')
                            ->rows(3)
                            ->columnSpanFull(),

                        Hidden::make('user_id')
                            ->default(auth()->id()),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('prescription_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('illnessModel.name')
                    ->label('Diagnosis')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('doctor_name')
                    ->label('Doctor')
                    ->searchable(),

                TextColumn::make('medications.name')
                    ->label('Meds')
                    ->badge()
                    ->separator(','),

                TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->money('NGN')
                    ->state(fn($record) => (float)$record->lab_test_cost + (float)$record->drug_cost)
                    ->color('success'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Prescription')
                    ->icon('heroicon-m-plus')
                    ->modalWidth('4xl'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->modalWidth('4xl'),
                DeleteAction::make(),
            ]);
    }
}
