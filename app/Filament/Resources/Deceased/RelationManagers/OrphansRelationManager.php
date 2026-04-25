<?php

namespace App\Filament\Resources\Deceased\RelationManagers;

use App\Filament\Resources\Deceased\DeceasedResource;
use App\Services\RegistrationNumberService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrphansRelationManager extends RelationManager
{
    protected static string $relationship = 'orphans';
    protected static ?string $relatedResource = DeceasedResource::class;
    protected static ?string $recordTitleAttribute = 'full_name';
    protected static ?string $title = 'Orphans';
    protected static string|null|\BackedEnum $icon = 'heroicon-o-user-group';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Section: Personal Information
                Section::make('Personal Information')
                    ->description('Primary identification and demographic details.')
                    ->icon('heroicon-m-user')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('first_name')
                                    ->required()
                                    ->maxLength(100)
                                    ->autofocus(),
                                TextInput::make('middle_name')
                                    ->maxLength(100),
                                TextInput::make('last_name')
                                    ->required()
                                    ->maxLength(100),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('gender')
                                    ->options(\App\Enums\Gender::class)
                                    ->required()
                                    ->native(false),

                                DatePicker::make('birth_date')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->closeOnDateSelection(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('nin')
                                    ->label('NIN')
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('11-digit NIN')
                                    ->maxLength(20)
                                    ->nullable(),

                                TextInput::make('reg_no')
                                    ->label('Registration Number')
                                    ->placeholder('Auto-generated')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),

                // Section: Status & Marriage
                Section::make('Status & Eligibility')
                    ->description('Benefit eligibility and marital status information.')
                    ->icon('heroicon-m-check-badge')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_eligible')
                                    ->label('Eligible for Benefits')
                                    ->helperText('Verify if this orphan meets current support criteria.')
                                    ->default(true),

                                Toggle::make('is_married')
                                    ->label('Currently Married')
                                    ->default(false)
                                    ->live(),
                            ]),

                        DatePicker::make('married_at')
                            ->label('Marriage Date')
                            ->native(false)
                            ->visible(fn (Get $get): bool => $get('is_married'))
                            ->columnSpanFull(),
                    ]),

                // Section: Education
                Section::make('Education & Vocational Training')
                    ->description('Academic background and professional skills.')
                    ->icon('heroicon-m-academic-cap')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('western_education_id')
                                    ->relationship('westernEducation', 'school_name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->createOptionForm([
                                        TextInput::make('school_name')->required(),
                                    ]),

                                Select::make('islamiyya_education_id')
                                    ->relationship('islamiyyaEducation', 'islamiyya_name')
                                    ->searchable()
                                    ->preload()
                                    ->nullable()
                                    ->createOptionForm([
                                        TextInput::make('islamiyya_name')->required(),
                                    ]),
                            ]),

                        CheckboxList::make('vocationalSkills')
                            ->relationship('vocationalSkills', 'name')
                            ->searchable()
                            ->columns(3)
                            ->bulkToggleable()
                            ->columnSpanFull(),
                    ]),

                // Section: Documents & Address
                Section::make('Documentation & Contact')
                    ->description('Physical records and residential details.')
                    ->icon('heroicon-m-document-duplicate')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                FileUpload::make('picture_url')
                                    ->label('Profile Photo')
                                    ->image()
                                    ->disk('public')
                                    ->directory('orphan-photos')
                                    ->visibility('public')
                                    ->nullable()
                                    ->storeFileNamesIn('picture_original_name') // optional but stabilizes state
                                    ->dehydrated(true)
                                    ->preserveFilenames()
                                    ->imageEditor()
                                    ->circleCropper(),
//
//                                FileUpload::make('picture_url')
//                                    ->label('Profile Picture')
//                                    ->image()
//                                    ->avatar()
//                                    ->disk('public')
//                                    ->directory('orphan-photos')
//                                    ->visibility('public')
//                                    ->imageEditor()
//                                    ->circleCropper()
//                                    ->nullable(),

                                FileUpload::make('birth_certificate_path')
                                    ->label('Birth Certificate')
                                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                                    ->directory('certificates')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->maxSize(1024)
                                    ->nullable(),
                            ]),

                        Textarea::make('address')
                            ->label('Full Residential Address')
                            ->placeholder('Enter detailed address with landmarks...')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('picture_url')
                    ->label('Photo')
                    ->circular()
                    ->disk('public'),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'middle_name', 'last_name'])
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('gender')
                    ->badge()
                    ->color(fn ($state): string => match($state?->value) {
                        'MALE' => 'primary',
                        'FEMALE' => 'danger',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('age')
                    ->label('Age')
                    ->sortable('birth_date'),

                Tables\Columns\TextColumn::make('reg_no')
                    ->label('Reg No')
                    ->searchable()
                    ->badge(),

                Tables\Columns\IconColumn::make('is_eligible')
                    ->label('Eligible')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_married')
                    ->label('Married')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Add Orphan')
                    ->icon('heroicon-m-plus')
                    ->mutateDataUsing(function (array $data, RelationManager $livewire): array {

                        $deceased = $livewire->getOwnerRecord();

                        $generated = app(RegistrationNumberService::class)
                            ->generateOrphanData($deceased);

                        return array_merge($data, $generated);
                    }),
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
