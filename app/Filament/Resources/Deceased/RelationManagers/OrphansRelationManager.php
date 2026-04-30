<?php

namespace App\Filament\Resources\Deceased\RelationManagers;

use App\Filament\Resources\Deceased\DeceasedResource;
use App\Models\Orphan;
use App\Services\RegistrationNumberService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
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
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('gender')
                    ->badge(),

                Tables\Columns\TextColumn::make('age')
                    ->label('Age')
                    ->state(fn($record) => $record->age ?? 'N/A'),

                Tables\Columns\TextColumn::make('reg_no')
                    ->label('Reg No')
                    ->badge(),

                Tables\Columns\IconColumn::make('is_eligible')
                    ->label('Eligible')
                    ->boolean()
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Orphan')
                    ->icon('heroicon-m-plus')
                    ->modalWidth('4xl')
                    ->mutateDataUsing(function (array $data, RelationManager $livewire): array {

                        $deceased = $livewire->getOwnerRecord();

                        $generated = app(RegistrationNumberService::class)
                            ->generateOrphanData($deceased);

                        return array_merge($data, $generated);
                    })
            ])
            ->recordActions([
                // IMPROVED MEDICAL RECORDS ACTION
                Action::make('manageMedical')
                    ->label('Medical')
                    ->icon('heroicon-m-beaker')
                    ->color('success')
                    ->modalHeading(fn(Orphan $record) => "Medical History: {$record->full_name}")
                    ->modalWidth('5xl')
                    ->modalSubmitActionLabel('Save Updates')
                    ->schema([
                        Repeater::make('prescriptions')
                            ->relationship('prescriptions')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('doctor_name')
                                        ->required()
                                        ->placeholder('Attending Doctor'),
                                    TextInput::make('illness')
                                        ->label('Diagnosis')
                                        ->required()
                                        ->placeholder('Illness or reason for visit'),
                                    DatePicker::make('prescription_date')
                                        ->default(now())
                                        ->required()
                                        ->native(false),
                                ]),
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
                                Select::make('medications')
                                    ->multiple()
                                    ->relationship('medications', 'name')
                                    ->preload()
                                    ->searchable()
                                    ->hint('Search by drug name.'),
                                Textarea::make('note')
                                    ->label('Prescription Note')
                                    ->rows(2)
                                    ->placeholder('Dosage details or observations...')
                                    ->columnSpanFull(),
                                Hidden::make('user_id')
                                    ->default(auth()->id()),
                            ])
                            ->itemLabel(fn(array $state): ?string => ($state['illness'] ?? null) . ($state['prescription_date'] ? " (" . date('d/m/Y', strtotime($state['prescription_date'])) . ")" : ""))
                            ->collapsible()
                            ->collapsed()
                            ->cloneable()
                            ->addActionLabel('New Medical Record'),
                    ])
                    ->action(fn(Orphan $record) => $record->touch()),

                EditAction::make()->modalWidth('4xl'),
                DeleteAction::make(),
            ]);
    }

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
                                    ->maxLength(100),
                                TextInput::make('middle_name')
                                    ->maxLength(100),
                                TextInput::make('last_name')
                                    ->required()
                                    ->maxLength(100),
                            ]),

                        Grid::make(3)
                            ->schema([
                                Select::make('gender')
                                    ->options(\App\Enums\Gender::class)
                                    ->required()
                                    ->native(false),

                                DatePicker::make('birth_date')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('d/m/Y'),

                                TextInput::make('child_sequence')
                                    ->label('Sequence')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->hint('Position among siblings'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('nin')
                                    ->label('NIN')
                                    ->unique(ignoreRecord: true)
                                    ->placeholder('11-digit NIN')
                                    ->maxLength(20),

                                TextInput::make('reg_no')
                                    ->label('Registration Number')
                                    ->placeholder('Auto-generated')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ]),

                // Section: Status & Eligibility
                Section::make('Status & Eligibility')
                    ->icon('heroicon-m-check-badge')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_eligible')
                                    ->label('Eligible for Support')
                                    ->default(true)
                                    ->required(),

                                Toggle::make('is_married')
                                    ->label('Married')
                                    ->default(false)
                                    ->required()
                                    ->live(),
                            ]),

                        DatePicker::make('married_at')
                            ->label('Marriage Date')
                            ->native(false)
                            ->visible(fn(Get $get) => $get('is_married'))
                            ->required(fn(Get $get) => $get('is_married'))
                            ->columnSpanFull(),
                    ]),

                // Section: Unified Education (Fixed to match model)
                Section::make('Education History')
                    ->description('Current and past enrollments.')
                    ->icon('heroicon-m-academic-cap')
                    ->schema([
                        Repeater::make('educations')
                            ->relationship('educations')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('institution_id')
                                        ->relationship('institution', 'name')
                                        ->required()
                                        ->searchable()
                                        ->preload(),
                                    TextInput::make('level')
                                        ->label('Level/Class')
                                        ->placeholder('e.g. Primary 4, JSS 2')
                                        ->required(),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('school_fee')
                                        ->numeric()
                                        ->prefix('₦'),
                                    Toggle::make('is_current')
                                        ->label('Current Enrollment')
                                        ->default(true),
                                ]),
                            ])
                            ->itemLabel(fn(array $state): ?string => $state['level'] ?? null)
                            ->collapsible()
                            ->collapsed()
                            ->addActionLabel('Add Enrollment Record')
                            ->columnSpanFull(),
                    ]),

                // Section: Vocational Skills
                Section::make('Vocational Training')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        CheckboxList::make('vocationalSkills')
                            ->relationship('vocationalSkills', 'name')
                            ->searchable()
                            ->columns(3)
                            ->bulkToggleable()
                            ->columnSpanFull(),
                    ]),

                // Section: Documentation & Address
                Section::make('Documents & Address')
                    ->icon('heroicon-m-document-duplicate')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                FileUpload::make('picture_url')
                                    ->label('Profile Photo')
                                    ->image()
                                    ->avatar()
                                    ->disk('public')
                                    ->directory('orphan-photos')
                                    ->imageEditor()
                                    ->circleCropper(),

                                Section::make('Birth Certificate')
                                    ->compact()
                                    ->schema([
                                        Toggle::make('has_birth_cert')
                                            ->label('Has Birth Certificate?')
                                            ->live(),
                                        FileUpload::make('birth_certificate_path')
                                            ->label('Certificate Scan')
                                            ->visible(fn($get) => $get('has_birth_cert'))
                                            ->directory('birth-certificates')
                                            ->disk('public')
                                            ->acceptedFileTypes(['application/pdf', 'image/*']),
                                    ])->columns(2),
                            ]),

                        Textarea::make('address')
                            ->label('Residential Address')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
