<?php

namespace App\Filament\Resources\Widows\Schemas;

use App\Models\Deceased;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WidowForm
{
    public static function configure(Schema $schema, bool $includeDeceased = true): Schema
    {
        $familyFields = [];

        if ($includeDeceased) {
            $familyFields[] = Select::make('deceased_id')
                ->label('Deceased')
                ->relationship(
                    name: 'deceased',
                    titleAttribute: 'id',
                    modifyQueryUsing: fn ($query) => $query
                        ->orderBy('first_name')
                        ->orderBy('last_name')
                )
                ->getOptionLabelFromRecordUsing(
                    fn (Deceased $record): string => $record->full_name
                        ?: trim(
                            collect([
                                $record->first_name,
                                $record->middle_name,
                                $record->last_name,
                            ])
                                ->filter()
                                ->implode(' ')
                        )
                        ?: 'Unnamed deceased record'
                )
                ->searchable([
                    'full_name',
                    'first_name',
                    'middle_name',
                    'last_name',
                ])
                ->preload()
                ->required();
        }

        $familyFields[] = TextInput::make('child_sequence')
            ->label('Sequence Order')
            ->placeholder('Auto-calculated')
            ->numeric()
            ->disabled()
            ->dehydrated(false);

        $familyFields[] = Textarea::make('address')
            ->label('Residential Address')
            ->placeholder('Enter detailed address with landmarks...')
            ->rows(3)
            ->required()
            ->columnSpanFull();

        return $schema
            ->components([
                Section::make('Personal Information')
                    ->description('Primary identification and demographic details.')
                    ->icon('heroicon-m-user-circle')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('first_name')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('middle_name')
                                ->maxLength(100),
                            TextInput::make('last_name')
                                ->required()
                                ->maxLength(100),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('nin')
                                ->label('NIN')
                                ->unique(ignoreRecord: true)
                                ->placeholder('11-digit National Identity Number')
                                ->maxLength(11)
                                ->required(),

                            TextInput::make('reg_no')
                                ->label('Registration Number')
                                ->placeholder('Auto-generated on creation')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    ]),

                Section::make('Status & Skills')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('is_eligible')
                                ->label('Eligible for Support')
                                ->default(true)
                                ->inline(false),

                            Toggle::make('is_married')
                                ->label('Remarried')
                                ->default(false)
                                ->live()
                                ->inline(false),
                        ]),

                        DatePicker::make('married_at')
                            ->label('Date of New Marriage')
                            ->visible(fn ($get) => $get('is_married'))
                            ->required(fn ($get) => $get('is_married'))
                            ->native(false),

                        TagsInput::make('skills')
                            ->label('Vocational Skills / Profession')
                            ->placeholder('Add a skill...')
                            ->separator(',')
                            ->columnSpanFull(),
                    ]),

                Section::make('Family Link & Context')
                    ->icon('heroicon-m-home-modern')
                    ->schema($familyFields),

                Section::make('Documents')
                    ->icon('heroicon-m-photo')
                    ->schema([
                        FileUpload::make('picture_url')
                            ->label('Profile Picture')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->avatar()
                            ->directory('widow-photos')
                            ->disk('public')
                            ->visibility('public')
                            ->imageEditor()
                            ->circleCropper()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
