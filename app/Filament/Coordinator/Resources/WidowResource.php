<?php

namespace App\Filament\Coordinator\Resources;

use App\Filament\Coordinator\Concerns\ZoneScoped;
use App\Models\Widow;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WidowResource extends Resource
{
    use ZoneScoped;

    protected static ?string $model = Widow::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-heart';

    protected static string|null|\UnitEnum $navigationGroup = 'Beneficiary Registration';

    protected static ?int $navigationSort = 3;

    /**
     * Zone Scoping Logic
     */
    protected static function applyZoneScope(Builder $query, string $zoneId): Builder
    {
        return $query->whereHas('deceased', fn (Builder $q) => $q->where('zone_id', $zoneId));
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
        if (! $user) {
            return false;
        }
        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return $record->deceased?->zone_id === $user->coordinatedZone?->id;
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
        $coordinatorZoneId = auth()->user()?->coordinatedZone?->id;

        return $schema
            ->schema([
                Section::make('Household Context')
                    ->description('Identify the late spouse and verify the zone registration.')
                    ->icon('heroicon-m-home-modern')
                    ->schema([
                        Forms\Components\Select::make('deceased_id')
                            ->label('Late Spouse (Deceased Head)')
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
                    ->description('Primary identification and demographic details.')
                    ->icon('heroicon-m-user-circle')
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
                                ->maxLength(11)
                                ->required(),

                            Forms\Components\TextInput::make('reg_no')
                                ->label('Registration Number')
                                ->placeholder('Auto-generated')
                                ->disabled()
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('child_sequence')
                                ->label('Sequence Order')
                                ->helperText('Wife order (1st, 2nd, etc.)')
                                ->numeric()
                                ->disabled()
                                ->dehydrated(false),
                        ]),
                    ]),

                Section::make('Professional & Marital Status')
                    ->icon('heroicon-m-briefcase')
                    ->schema([
                        Forms\Components\TagsInput::make('skills')
                            ->label('Vocational Skills / Profession')
                            ->placeholder('Add skill (e.g. Tailoring)')
                            ->columnSpanFull(),

                        Grid::make(3)->schema([
                            Forms\Components\Toggle::make('is_married')
                                ->label('Has Remarried')
                                ->live(),

                            Forms\Components\DatePicker::make('married_at')
                                ->label('New Marriage Date')
                                ->native(false)
                                ->visible(fn (Get $get) => (bool) $get('is_married'))
                                ->required(fn (Get $get) => (bool) $get('is_married')),

                            Forms\Components\Toggle::make('is_eligible')
                                ->label('Eligible for Support')
                                ->default(true),
                        ]),
                    ]),

                Section::make('Location & Profile')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\Textarea::make('address')
                                ->placeholder('Current residential address...')
                                ->rows(3)
                                ->required(),

                            Forms\Components\FileUpload::make('picture_url')
                                ->label('Profile Photo')
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->avatar()
                                ->directory('widow-photos')
                                ->disk('public')
                                ->visibility('public')
                                ->maxSize(5120),
                        ]),
                    ]),

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
                    ->description(fn (Widow $record) => "Reg: {$record->reg_no}"),

                Tables\Columns\TextColumn::make('deceased.full_name')
                    ->label('Deceased Spouse')
                    ->searchable()
                    ->description(fn (Widow $record) => $record->deceased?->reg_no),

                Tables\Columns\TextColumn::make('nin')
                    ->label('NIN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_eligible')
                    ->label('Eligible')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('is_married')
                    ->label('Remarried')
                    ->boolean()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('loans_count')
                    ->counts('widowLoans')
                    ->label('Loans')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_eligible')->label('Eligible Only'),
                Tables\Filters\TernaryFilter::make('is_married')->label('Remarried Only'),
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
                    ->visible(fn ($record) => ! $record->is_married)
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
                                if (! $record->is_married) {
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
            'index' => \App\Filament\Coordinator\Resources\WidowResource\Pages\ListWidows::route('/'),
            'create' => \App\Filament\Coordinator\Resources\WidowResource\Pages\CreateWidow::route('/create'),
            'edit' => \App\Filament\Coordinator\Resources\WidowResource\Pages\EditWidow::route('/{record}/edit'),
            'view' => \App\Filament\Coordinator\Resources\WidowResource\Pages\ViewWidow::route('/{record}'),
        ];
    }
}
