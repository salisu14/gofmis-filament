<?php

namespace App\Filament\Coordinator\Resources;

use App\Enums\VulnerabilityStatus;
use App\Filament\Coordinator\Concerns\ZoneScoped;
use App\Models\Deceased;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DeceasedResource extends Resource
{
    use ZoneScoped;

    protected static ?string $model = Deceased::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-user-minus';

    protected static string|null|\UnitEnum $navigationGroup = 'Beneficiary Registration';

    protected static ?int $navigationSort = 1;

    /**
     * Zone Scoping Logic
     */
    protected static function applyZoneScope(Builder $query, string $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    protected static function getRecordZoneId($record): ?string
    {
        return $record->zone_id;
    }

    /* -------------------------------------------------------------------------
     | PERMISSIONS
     ------------------------------------------------------------------------- */

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['coordinator', 'admin', 'super_admin']) ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return $user->managesZone($record->zone_id);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    /* -------------------------------------------------------------------------
     | FORM SCHEMA
     ------------------------------------------------------------------------- */

    public static function form(Schema $schema): Schema
    {
        $coordinatorZoneId = auth()->user()?->coordinatedZone?->id;

        return $schema
            ->schema([
                Section::make('Primary Identity')
                    ->description('Identification details of the deceased household head.')
                    ->icon('heroicon-m-user')
                    ->schema([
                        Grid::make(3)->schema([
                            Forms\Components\TextInput::make('first_name')->required()->maxLength(100),
                            Forms\Components\TextInput::make('middle_name')->maxLength(100),
                            Forms\Components\TextInput::make('last_name')->required()->maxLength(100),
                        ]),

                        Grid::make(3)->schema([
                            Forms\Components\TextInput::make('nin')
                                ->label('NIN')
                                ->unique(ignoreRecord: true)
                                ->placeholder('11-digit identity number')
                                ->maxLength(20),

                            Forms\Components\TextInput::make('reg_no')
                                ->label('Registration Number')
                                ->placeholder('Auto-generated')
                                ->disabled()
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('age')
                                ->label('Age at Passing')
                                ->numeric()
                                ->required(),
                        ]),
                    ]),

                Section::make('Circumstances & Profession')
                    ->icon('heroicon-m-information-circle')
                    ->schema([
                        Grid::make(3)->schema([
                            Forms\Components\DatePicker::make('date_registered')
                                ->label('Date of Registration')
                                ->default(now())
                                ->required()
                                ->native(false),

                            Forms\Components\TextInput::make('death_cause')
                                ->label('Cause of Death')
                                ->placeholder('e.g. Natural, Accident'),

                            Forms\Components\TextInput::make('death_place')
                                ->label('Place of Death')
                                ->placeholder('Hospital or Home address'),
                        ]),

                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('occupation')
                                ->label('Former Occupation')
                                ->placeholder('e.g. Farmer, Teacher'),

                            Forms\Components\Select::make('vulnerability_status')
                                ->label('Vulnerability Level')
                                ->options(VulnerabilityStatus::class)
                                ->required()
                                ->native(false),
                        ]),
                    ]),

                Section::make('Family & Legal')
                    ->icon('heroicon-m-home-modern')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('number_of_widows_left')
                                ->label('Widows Remaining')
                                ->numeric()
                                ->default(0)
                                ->required(),

                            Forms\Components\TextInput::make('number_of_orphans_left')
                                ->label('Orphans Remaining')
                                ->numeric()
                                ->default(0)
                                ->required(),
                        ]),

                        Grid::make(2)->schema([
                            Forms\Components\Toggle::make('has_death_cert')
                                ->label('Death Certificate Available')
                                ->live(),

                            Forms\Components\FileUpload::make('death_cert_url')
                                ->label('Death Certificate Scan')
                                ->directory('death-certificates')
                                ->disk('public')
                                ->visibility('public')
                                ->acceptedFileTypes(['application/pdf', 'image/*'])
                                ->visible(fn (Get $get) => (bool) $get('has_death_cert'))
                                ->preserveFilenames()
                                ->dehydrated(fn ($state) => filled($state)),
                        ]),
                    ]),

                Section::make('Contact & Guardian')
                    ->description('Emergency contact or current household guardian details.')
                    ->icon('heroicon-m-phone')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('guardian_name')
                                ->label('Guardian Full Name')
                                ->placeholder('Current caregiver name'),

                            Forms\Components\TextInput::make('guardian_phone')
                                ->label('Guardian Phone')
                                ->tel()
                                ->placeholder('+234...'),
                        ]),

                        Forms\Components\Textarea::make('address')
                            ->label('Last Residential Address')
                            ->rows(3)
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Hidden::make('zone_id')
                    ->default($coordinatorZoneId),

                Forms\Components\Hidden::make('registered_by')
                    ->default(auth()->id()),
            ]);
    }

    /* -------------------------------------------------------------------------
     | TABLE CONFIGURATION
     ------------------------------------------------------------------------- */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Deceased $record) => "Reg: {$record->reg_no}"),

                Tables\Columns\TextColumn::make('vulnerability_status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('widows_count')
                    ->counts('widows')
                    ->label('Widows')
                    ->badge()
                    ->color('warning')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('orphans_count')
                    ->counts('orphans')
                    ->label('Orphans')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('has_death_cert')
                    ->label('Cert')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('date_registered')
                    ->label('Registered')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('vulnerability_status')
                    ->options(VulnerabilityStatus::class),
                TernaryFilter::make('has_death_cert')
                    ->label('Has Certificate'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Coordinator\Resources\DeceasedResource\Pages\ListDeceaseds::route('/'),
            'create' => \App\Filament\Coordinator\Resources\DeceasedResource\Pages\CreateDeceased::route('/create'),
            'edit' => \App\Filament\Coordinator\Resources\DeceasedResource\Pages\EditDeceased::route('/{record}/edit'),
            'view' => \App\Filament\Coordinator\Resources\DeceasedResource\Pages\ViewDeceased::route('/{record}'),
        ];
    }
}
