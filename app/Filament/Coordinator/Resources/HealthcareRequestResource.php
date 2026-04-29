<?php
// app/Filament/Coordinator/Resources/HealthcareRequestResource.php

namespace App\Filament\Coordinator\Resources;

use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\CreateHealthcareRequest;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\EditHealthcareRequest;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\ListHealthcareRequests;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\ViewHealthcareRequest;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\Widow;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HealthcareRequestResource extends Resource
{
    protected static ?string $model = Prescription::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-heart';
    protected static ?string $navigationLabel = 'Healthcare Requests';
    protected static ?string $modelLabel = 'Healthcare Request';
    protected static ?string $pluralModelLabel = 'Healthcare Requests';
    protected static string|null|\UnitEnum $navigationGroup = 'Intervention Requests';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        $zoneId = auth()->user()?->zone_id;
        $isAdmin = auth()->user()?->hasRole(['admin', 'super-admin']);

        $query = parent::getEloquentQuery();

        if ($isAdmin || !$zoneId) {
            return $query;
        }

        // Filter prescriptions for orphans/widows in coordinator's zone
        return $query->where(function (Builder $q) use ($zoneId) {
            $q->whereHas('prescribable', function (Builder $q2) use ($zoneId) {
                // Check if prescribable is Orphan
                $q2->where(function (Builder $q3) use ($zoneId) {
                    $q3->where('prescribable_type', Orphan::class)
                        ->whereHas('deceased', fn($q4) => $q4->where('zone_id', $zoneId));
                })->orWhere(function (Builder $q3) use ($zoneId) {
                    // Check if prescribable is Widow
                    $q3->where('prescribable_type', Widow::class)
                        ->whereHas('deceased', fn($q4) => $q4->where('zone_id', $zoneId));
                });
            });
        });
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['coordinator', 'admin', 'super-admin']) ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if ($user->hasRole(['admin', 'super-admin'])) return true;

        // Only allow editing recent prescriptions
        return $record->created_at->diffInDays(now()) <= 7;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole(['admin', 'super-admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $user = auth()->user();
        $zoneId = $user->zone_id;

        return $schema
            ->schema([
                Section::make('Patient Information')
                    ->schema([
                        Forms\Components\Select::make('patient_type')
                            ->label('Patient Type')
                            ->options([
                                'orphan' => 'Orphan',
                                'widow' => 'Widow',
                            ])
                            ->required()
                            ->live()
                            ->native(false),

                        Select::make('prescribable_id')
                            ->label('Patient')
                            ->options(function (Get $get) use ($zoneId) {
                                $type = $get('patient_type');
                                if (!$type) return [];

                                if ($type === 'orphan') {
                                    return Orphan::whereHas('deceased', fn($q) => $q->where('zone_id', $zoneId))
                                        ->where('is_eligible', true)
                                        ->pluck('full_name', 'id');
                                }

                                return Widow::whereHas('deceased', fn($q) => $q->where('zone_id', $zoneId))
                                    ->where('is_eligible', true)
                                    ->pluck('full_name', 'id');
                            })
                            ->searchable()
                            ->preload()
                            ->required(),

                        Hidden::make('prescribable_type')
                            ->default(fn(Get $get) => $get('patient_type') === 'orphan'
                                ? Orphan::class
                                : Widow::class
                            ),
                    ]),

                Section::make('Prescription Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('doctor_name')
                            ->label('Doctor/Hospital Name')
                            ->required()
                            ->placeholder('Dr. Name or Hospital'),

                        Forms\Components\TextInput::make('illness')
                            ->label('Illness/Diagnosis')
                            ->required()
                            ->placeholder('e.g., Malaria, Typhoid'),

                        Forms\Components\TextInput::make('lab_test_cost')
                            ->label('Lab Test Cost (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn(Set $set, Get $get) =>
                            $set('total_cost', (float) ($get('lab_test_cost') ?? 0) + (float) ($get('drug_cost') ?? 0))
                            ),

                        TextInput::make('drug_cost')
                            ->label('Drug Cost (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn(Set $set, Get $get) =>
                            $set('total_cost', (float) ($get('lab_test_cost') ?? 0) + (float) ($get('drug_cost') ?? 0))
                            ),

                        Forms\Components\TextInput::make('total_cost')
                            ->label('Total Cost (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(0),

                        DatePicker::make('prescription_date')
                            ->label('Prescription Date')
                            ->required()
                            ->default(now()),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        Textarea::make('note')
                            ->label('Notes')
                            ->rows(3)
                            ->placeholder('Additional notes about the prescription...'),
                    ]),

                Hidden::make('user_id')
                    ->default($user->id),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prescribable.full_name')
                    ->label('Patient')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('prescribable_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn($state) => class_basename($state))
                    ->colors([
                        'info' => 'Orphan',
                        'warning' => 'Widow',
                    ]),

                Tables\Columns\TextColumn::make('illness')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('doctor_name')
                    ->label('Doctor/Hospital')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('total_cost')
                    ->money('NGN')
                    ->sortable(),

                Tables\Columns\TextColumn::make('prescription_date')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('prescribable_type')
                    ->label('Patient Type')
                    ->options([
                        Orphan::class => 'Orphan',
                        Widow::class => 'Widow',
                    ]),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn($q) => $q->whereMonth('prescription_date', now()->month)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn($record) => $record->created_at->diffInDays(now()) <= 7),
            ])
            ->defaultSort('prescription_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHealthcareRequests::route('/'),
            'create' => CreateHealthcareRequest::route('/create'),
            'edit' => EditHealthcareRequest::route('/{record}/edit'),
            'view' => ViewHealthcareRequest::route('/{record}'),
        ];
    }
}
