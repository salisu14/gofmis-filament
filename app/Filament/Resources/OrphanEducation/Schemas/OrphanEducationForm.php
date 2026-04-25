<?php

namespace App\Filament\Resources\OrphanEducation\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class OrphanEducationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Enrollment Information')
                    ->description('Link the orphan to an educational institution and define their academic level.')
                    ->icon('heroicon-m-academic-cap')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('orphan_id')
                                ->label('Student')
                                ->relationship('orphan', 'full_name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabledOn('edit'),

                            Select::make('institution_id')
                                ->label('Institution')
                                ->relationship('institution', 'name')
                                ->searchable()
                                ->preload()
                                ->required(),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('level')
                                ->label('Level / Grade')
                                ->placeholder('e.g. Primary 5, SS 2')
                                ->required(),

                            TextInput::make('class_level')
                                ->label('Class / Section')
                                ->placeholder('e.g. Blue House, Science A'),
                        ]),
                    ]),

                Section::make('Financial Configuration')
                    ->description('Set the base fees and any sponsorship support levels.')
                    ->icon('heroicon-m-banknotes')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('school_fee')
                                ->label('Standard School Fee')
                                ->numeric()
                                ->prefix('₦')
                                ->required(),

                            Select::make('fee_frequency')
                                ->label('Payment Frequency')
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
                                ->label('Under Sponsorship Support')
                                ->helperText('Enable if an external sponsor or the NGO covers a portion of the fee.')
                                ->reactive()
                                ->default(false),

                            TextInput::make('support_amount')
                                ->label('Sponsorship Amount')
                                ->numeric()
                                ->prefix('₦')
                                ->visible(fn (Get $get) => $get('is_fee_supported'))
                                ->required(fn (Get $get) => $get('is_fee_supported')),
                        ])->columns(2),
                    ]),

                Section::make('Timeline & Lifecycle')
                    ->icon('heroicon-m-calendar-days')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('is_current')
                                ->label('Currently Active Enrollment')
                                ->default(true)
                                ->inline(false),

                            DatePicker::make('started_at')
                                ->label('Start Date')
                                ->native(false),

                            DatePicker::make('ended_at')
                                ->label('Completion Date')
                                ->native(false),
                        ]),
                    ]),
            ]);
    }
}
