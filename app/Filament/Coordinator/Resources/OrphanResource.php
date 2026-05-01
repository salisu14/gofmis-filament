<?php

namespace App\Filament\Coordinator\Resources;

use App\Filament\Coordinator\Concerns\ZoneScoped;
use App\Enums\Gender;
use App\Models\Orphan;
use App\Models\VocationalSkill;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrphanResource extends Resource
{
    use ZoneScoped;

    protected static ?string $model = Orphan::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-user-group';
    protected static string|null|\UnitEnum $navigationGroup = 'Beneficiary Registration';
    protected static ?int $navigationSort = 2;

    /**
     * Zone Scoping Logic
     */
    protected static function applyZoneScope(Builder $query, string $zoneId): Builder
    {
        return $query->whereHas('deceased', function ($q) use ($zoneId) {
            $q->where('zone_id', $zoneId);
        });
    }

    protected static function getRecordZoneId($record): ?string
    {
        return $record->deceased?->zone_id;
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
        if (!$user) return false;
        if ($user->hasAnyRole(['admin', 'super_admin'])) return true;

        return $record->deceased?->zone_id === $user->zone_id;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole(['admin', 'super_admin']) ?? false;
    }

    /* -------------------------------------------------------------------------
     | FORM SCHEMA
     ------------------------------------------------------------------------- */

    public static function form(Schema $schema): Schema
    {
        $coordinatorZoneId = auth()->user()?->zone_id;

        return $schema
            ->schema([
                Section::make('Household Context')
                    ->description('Link the orphan to a registered household head.')
                    ->icon('heroicon-m-home-modern')
                    ->schema([
                        Select::make('deceased_id')
                            ->label('Family Head (Deceased)')
                            ->relationship(
                                'deceased',
                                'full_name',
                                fn (Builder $query) => $query->when($coordinatorZoneId, fn ($q) => $q->where('zone_id', $coordinatorZoneId))
                            )
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->full_name} ({$record->reg_no})")
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Personal Information')
                    ->description('Basic demographic and identification data.')
                    ->icon('heroicon-m-user')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('first_name')->required()->maxLength(100),
                            TextInput::make('middle_name')->maxLength(100),
                            TextInput::make('last_name')->required()->maxLength(100),
                        ]),

                        Grid::make(3)->schema([
                            Forms\Components\Select::make('gender')
                                ->options(Gender::class)
                                ->required()
                                ->native(false),

                            DatePicker::make('birth_date')
                                ->required()
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set) {
                                    if ($state) {
                                        $set('age', Carbon::parse($state)->age);
                                    }
                                }),

                            TextInput::make('age')
                                ->label('Calculated Age')
                                ->numeric()
                                ->readOnly()
                                ->dehydrated(false) // 👈 important (don’t save manually)
                                ->helperText('Auto-calculated from birth date.'),
                        ]),

                        Grid::make(2)->schema([
                            TextInput::make('nin')
                                ->label('NIN')
                                ->maxLength(20)
                                ->unique(ignoreRecord: true)
                                ->placeholder('11-digit identity number'),

                            TextInput::make('reg_no')
                                ->label('Registration Number')
                                ->placeholder('Assigned on creation')
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    ]),

                Section::make('Life Status & Eligibility')
                    ->icon('heroicon-m-check-badge')
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('is_eligible')
                                ->label('Eligible for Support')
                                ->disabled() // Usually managed by admin verification
                                ->dehydrated(false),

                            Toggle::make('has_birth_cert')
                                ->label('Has Birth Certificate')
                                ->live(),

                            Toggle::make('is_married')
                                ->label('Currently Married')
                                ->live(),
                        ]),

                        Grid::make(2)->schema([
                            FileUpload::make('birth_certificate_path')
                                ->label('Birth Certificate Scan')
                                ->visible(fn (Get $get) => (bool) $get('has_birth_cert'))
                                ->directory('certificates')
                                ->disk('public')
                                ->acceptedFileTypes(['application/pdf', 'image/*']),

                            DatePicker::make('married_at')
                                ->label('Marriage Date')
                                ->native(false)
                                ->visible(fn (Get $get) => (bool) $get('is_married'))
                                ->required(fn (Get $get) => (bool) $get('is_married')),
                        ]),
                    ]),

                Section::make('Vocation & Education')
                    ->description('Current academic and skill-based tracking.')
                    ->icon('heroicon-m-academic-cap')
                    ->schema([
                        CheckboxList::make('vocationalSkills')
                            ->options(fn () => VocationalSkill::query()->orderBy('name')->pluck('name', 'id')->toArray())
                            ->columns(3)
                            ->searchable()
                            ->bulkToggleable(),

                        Repeater::make('educations')
                            ->relationship('educations')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('institution_id')
                                        ->relationship('institution', 'name')
                                        ->required()
                                        ->searchable(),
                                    TextInput::make('level')
                                        ->label('Class / Level')
                                        ->required(),
                                ]),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['level'] ?? null)
                            ->collapsible()
                            ->collapsed()
                            ->columnSpanFull(),
                    ]),

                Section::make('Location & Profile')
                    ->schema([
                        Textarea::make('address')
                            ->placeholder('Current residential address...')
                            ->rows(3)
                            ->columnSpanFull(),

                        FileUpload::make('picture_url')
                            ->label('Profile Photo')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->avatar()
                            ->directory('orphans')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120),
                    ]),

                Hidden::make('status')->default('active'),
            ]);
    }

    /* -------------------------------------------------------------------------
     | TABLE CONFIGURATION
     ------------------------------------------------------------------------- */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('picture_url')
                    ->label('')
                    ->circular()
                    ->disk('public')
                    ->visibility('public')
                    ->checkFileExistence(false),

                Tables\Columns\TextColumn::make('full_name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Orphan $record) => "Reg: {$record->reg_no}"),

                Tables\Columns\TextColumn::make('deceased.full_name')
                    ->label('Household Head')
                    ->searchable()
                    ->description(fn (Orphan $record) => $record->deceased?->reg_no),

                Tables\Columns\TextColumn::make('gender')
                    ->badge(),

                Tables\Columns\TextColumn::make('age')
                    ->label('Age')
                    ->state(fn($record) => $record->birth_date?->age)
                    ->sortable('birth_date')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active', 'approved' => 'success',
                        'pending', 'pending_review' => 'warning',
                        'rejected', 'inactive' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_eligible')
                    ->label('Eligible')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M, Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('gender')->options(Gender::class),
                Tables\Filters\SelectFilter::make('status'),
                Tables\Filters\TernaryFilter::make('is_eligible')->label('Eligible Only'),
                Tables\Filters\TernaryFilter::make('is_married')->label('Married Only'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('markAsMarried')
                    ->label('Mark Married')
                    ->icon('heroicon-m-heart')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Mark as Married')
                    ->modalDescription('This will revoke all benefits and eligibility. This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Mark as Married')
                    ->visible(fn($record) => !$record->is_married && (($record->gender->value ?? $record->gender) === Gender::FEMALE->value))
                    ->schema([
                        DatePicker::make('married_at')
                            ->label('Marriage Date')
                            ->default(now())
                            ->required(),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Optional notes about the marriage...')
                            ->rows(2),
                    ])
                    ->action(function ($record, array $data) {
                        $record->update([
                            'is_married' => true,
                            'married_at' => $data['married_at'] ?? now(),
                        ]);

                        // Call the model method to handle side effects
                        $record->markAsMarried($data['notes'] ?? null);

                        Notification::make()
                            ->title('Marked as Married')
                            ->body("{$record->full_name} has been marked as married and removed from benefits.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    BulkAction::make('markAsMarried')
                        ->label('Mark Selected as Married')
                        ->icon('heroicon-m-heart')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Mark Multiple as Married')
                        ->modalDescription('This will revoke benefits for all selected beneficiaries.')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                if (
                                    !$record->is_married
                                    && (($record->gender->value ?? $record->gender) === Gender::FEMALE->value)
                                ) {
                                    $record->markAsMarried();
                                }
                            }

                            Notification::make()
                                ->title('Completed')
                                ->body("{$records->count()} beneficiaries marked as married.")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Coordinator\Resources\OrphanResource\Pages\ListOrphans::route('/'),
            'create' => \App\Filament\Coordinator\Resources\OrphanResource\Pages\CreateOrphan::route('/create'),
            'edit' => \App\Filament\Coordinator\Resources\OrphanResource\Pages\EditOrphan::route('/{record}/edit'),
            'view' => \App\Filament\Coordinator\Resources\OrphanResource\Pages\ViewOrphan::route('/{record}'),
        ];
    }
}
