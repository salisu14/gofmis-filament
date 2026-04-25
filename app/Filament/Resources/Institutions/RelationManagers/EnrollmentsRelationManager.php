<?php

namespace App\Filament\Resources\Institutions\RelationManagers;

use App\Filament\Resources\Institutions\InstitutionResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    protected static ?string $recordTitleAttribute = 'level';
    protected static ?string $relatedResource = InstitutionResource::class;

    protected static ?string $title = 'Student Roster';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Enrollment Details')
                    ->description('Manage academic placement and fees for this student at this institution.')
                    ->schema([
                        // Select the orphan if creating a new enrollment from the institution side
                        Select::make('orphan_id')
                            ->relationship('orphan', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabledOn('edit'),

                        Grid::make(2)->schema([
                            TextInput::make('level')
                                ->label('Academic Level')
                                ->placeholder('e.g. Primary 4, JSS 2')
                                ->required(),

                            TextInput::make('class_level')
                                ->label('Class/Section')
                                ->placeholder('e.g. Blue, Section A'),
                        ]),
                    ]),

                Section::make('Financial Agreement')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('school_fee')
                                ->label('Base School Fee')
                                ->numeric()
                                ->prefix('₦')
                                ->required(),

                            Select::make('fee_frequency')
                                ->options([
                                    'monthly' => 'Monthly',
                                    'termly' => 'Termly',
                                    'yearly' => 'Yearly',
                                ])
                                ->required()
                                ->native(false),
                        ]),

                        Group::make()->schema([
                            Toggle::make('is_fee_supported')
                                ->label('Sponsorship Support Active')
                                ->reactive()
                                ->default(false),

                            TextInput::make('support_amount')
                                ->label('Support/Sponsorship Amount')
                                ->numeric()
                                ->prefix('₦')
                                ->visible(fn (Get $get) => $get('is_fee_supported')),
                        ])->columns(2),
                    ]),

                Section::make('Status & Dates')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('is_current')
                                ->label('Currently Enrolled')
                                ->default(true),

                            DatePicker::make('started_at')
                                ->label('Enrollment Date')
                                ->native(false),

                            DatePicker::make('ended_at')
                                ->label('Completion/Exit Date')
                                ->native(false),
                        ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('orphan.full_name')
                    ->label('Student Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('level')
                    ->label('Level')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('school_fee')
                    ->label('Fee Rate')
                    ->money('NGN')
                    ->description(fn ($record) => "Per {$record->fee_frequency}"),

                IconColumn::make('is_current')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('balance')
                    ->label('Outstanding Balance')
                    ->money('NGN')
                    ->color('danger')
                    ->alignEnd(),
            ])
            ->filters([
                TernaryFilter::make('is_current')
                    ->label('Current Students Only'),

                SelectFilter::make('level')
                    ->options(fn () => \App\Models\OrphanEducation::query()->distinct()->pluck('level', 'level')->toArray()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Enroll Student')
                    ->icon('heroicon-m-plus')
                    ->modalWidth('4xl'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('4xl'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
