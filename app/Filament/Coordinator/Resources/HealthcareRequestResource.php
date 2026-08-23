<?php

// app/Filament/Coordinator/Resources/HealthcareRequestResource.php

namespace App\Filament\Coordinator\Resources;

use App\Enums\PrescriptionStatus;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\CreateHealthcareRequest;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\EditHealthcareRequest;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\ListHealthcareRequests;
use App\Filament\Coordinator\Resources\HealthcareRequestResource\Pages\ViewHealthcareRequest;
use App\Models\Illness;
use App\Models\Orphan;
use App\Models\Prescription;
use App\Models\Widow;
use Filament\Actions\Action;
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
        $zoneId = auth()->user()?->coordinatedZone?->id;
        $isAdmin = auth()->user()?->hasAnyRole(['admin', 'super_admin']);

        $query = parent::getEloquentQuery();

        if ($isAdmin) {
            return $query;
        }

        if (! $zoneId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($zoneId) {
            $query
                ->where(function (Builder $query) use ($zoneId) {
                    $query->where('prescribable_type', Orphan::class)
                        ->whereHasMorph('prescribable', [Orphan::class], fn (Builder $query) => $query
                            ->whereHas('deceased', fn (Builder $query) => $query->where('zone_id', $zoneId)));
                })
                ->orWhere(function (Builder $query) use ($zoneId) {
                    $query->where('prescribable_type', Widow::class)
                        ->whereHasMorph('prescribable', [Widow::class], fn (Builder $query) => $query
                            ->whereHas('deceased', fn (Builder $query) => $query->where('zone_id', $zoneId)));
                });
        });
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        return $user?->hasAnyRole(['admin', 'super_admin'])
            || $user?->managesZone();
    }

    public static function canView($record): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return $user->managesZone(static::recordZoneId($record));
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (! $record || $record->isTreated()) {
            return false;
        }

        if ($user?->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        return $record->created_at->diffInDays(now()) <= 7 && $user?->managesZone(static::recordZoneId($record));
    }

    public static function canDelete($record): bool
    {
        if ($record?->isTreated()) {
            return false;
        }

        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    protected static function recordZoneId($record): ?string
    {
        if ($record->prescribable_type === Orphan::class || $record->prescribable_type === Widow::class) {
            return $record->prescribable?->deceased?->zone_id;
        }

        return null;
    }

    public static function form(Schema $schema): Schema
    {
        $user = auth()->user();
        $zoneId = $user?->coordinatedZone?->id;

        return $schema
            ->schema([
                Section::make('Treatment Outcome & Status')
                    ->description('Administration details and completion status.')
                    ->icon('heroicon-m-check-badge')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options(PrescriptionStatus::class)
                            ->enum(PrescriptionStatus::class)
                            ->disabled()
                            ->dehydrated(),

                        DatePicker::make('treated_at')
                            ->label('Treated Date')
                            ->disabled()
                            ->dehydrated(),

                        Textarea::make('treatment_notes')
                            ->label('Treatment Notes')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated()
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($record) => $record?->isTreated()),

                Section::make('Patient Information')
                    ->schema([
                        Select::make('prescribable_type')
                            ->label('Patient Category')
                            ->options([
                                Orphan::class => 'Orphan',
                                Widow::class => 'Widow',
                            ])
                            ->required()
                            ->disabledOn('edit')
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('prescribable_id', null))
                            ->native(false)
                            ->default(Orphan::class)
                            ->selectablePlaceholder(false),

                        Select::make('prescribable_id')
                            ->label('Patient')
                            ->disabledOn('edit')
                            ->options(function (Get $get) {
                                $type = $get('prescribable_type');
                                if (! $type) {
                                    return [];
                                }

                                $user = auth()->user();
                                $zoneId = $user?->coordinatedZone?->id;
                                $isAdmin = $user?->hasAnyRole(['admin', 'super_admin']);

                                if ($type === Orphan::class) {
                                    $query = Orphan::query()->where('is_eligible', true);
                                    if (! $isAdmin) {
                                        if (! $zoneId) {
                                            return [];
                                        }
                                        $query->whereHas('deceased', fn ($q) => $q->where('zone_id', $zoneId));
                                    }

                                    return $query->get()
                                        ->mapWithKeys(fn ($o) => [$o->id => "{$o->display_name} ({$o->reg_no})"]);
                                }

                                if ($type === Widow::class) {
                                    $query = Widow::query()->where('is_eligible', true);
                                    if (! $isAdmin) {
                                        if (! $zoneId) {
                                            return [];
                                        }
                                        $query->whereHas('deceased', fn ($q) => $q->where('zone_id', $zoneId));
                                    }

                                    return $query->get()
                                        ->mapWithKeys(fn ($w) => [$w->id => "{$w->display_name} ({$w->reg_no})"]);
                                }

                                return [];
                            })
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->getSearchResultsUsing(function (string $search, Get $get) {
                                $type = $get('prescribable_type');
                                if (! $type) {
                                    return [];
                                }

                                $user = auth()->user();
                                $zoneId = $user?->coordinatedZone?->id;
                                $isAdmin = $user?->hasAnyRole(['admin', 'super_admin']);

                                if ($type === Orphan::class) {
                                    $query = Orphan::query()
                                        ->where('is_eligible', true)
                                        ->where(function ($q) use ($search) {
                                            $q->where('full_name', 'like', "%{$search}%")
                                                ->orWhere('reg_no', 'like', "%{$search}%");
                                        });

                                    if (! $isAdmin) {
                                        if (! $zoneId) {
                                            return [];
                                        }
                                        $query->whereHas('deceased', fn ($q) => $q->where('zone_id', $zoneId));
                                    }

                                    return $query->limit(50)->get()
                                        ->mapWithKeys(fn ($o) => [$o->id => "{$o->full_name} ({$o->reg_no})"]);
                                }

                                if ($type === Widow::class) {
                                    $query = Widow::query()
                                        ->where('is_eligible', true)
                                        ->where(function ($q) use ($search) {
                                            $q->where('full_name', 'like', "%{$search}%")
                                                ->orWhere('reg_no', 'like', "%{$search}%");
                                        });

                                    if (! $isAdmin) {
                                        if (! $zoneId) {
                                            return [];
                                        }
                                        $query->whereHas('deceased', fn ($q) => $q->where('zone_id', $zoneId));
                                    }

                                    return $query->limit(50)->get()
                                        ->mapWithKeys(fn ($w) => [$w->id => "{$w->full_name} ({$w->reg_no})"]);
                                }

                                return [];
                            })
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        if (! $value) {
                                            return;
                                        }

                                        $user = auth()->user();
                                        $zoneId = $user?->coordinatedZone?->id;

                                        $type = $get('prescribable_type');
                                        if (! $type || ! in_array($type, [Orphan::class, Widow::class], true)) {
                                            $fail('The selected patient category is invalid.');

                                            return;
                                        }

                                        $model = $type::find($value);
                                        if (! $model) {
                                            $fail('The selected patient does not exist.');

                                            return;
                                        }

                                        if (! $model->is_eligible) {
                                            $fail('The selected patient is not eligible for healthcare requests.');

                                            return;
                                        }

                                        if (! $user?->hasAnyRole(['admin', 'super_admin'])) {
                                            $patientZoneId = $model->deceased?->zone_id;
                                            if (! $patientZoneId || $patientZoneId !== $zoneId) {
                                                $fail('You are not authorized to create a healthcare request for a beneficiary outside your assigned zone.');
                                            }
                                        }
                                    };
                                },
                            ]),
                    ]),

                Section::make('Prescription Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('doctor_name')
                            ->label('Doctor/Hospital Name')
                            ->required()
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->placeholder('Dr. Name or Hospital')
                            ->maxLength(255),

                        Select::make('illness_id')
                            ->label('Diagnosis')
                            ->relationship('illnessModel', 'name')
                            ->searchable()
                            ->preload()
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->required()
                            ->native(false)
                            ->getOptionLabelFromRecordUsing(fn (Illness $record) => "{$record->name} (".($record->category?->label() ?? 'Other').')'),

                        Select::make('medications')
                            ->label('Prescribed / Requested Medications')
                            ->multiple()
                            ->relationship('medications', 'name')
                            ->preload()
                            ->searchable()
                            ->native(false)
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->columnSpanFull()
                            ->hint('Select drugs from the pharmacy master list')
                            ->hintIcon('heroicon-m-information-circle'),

                        Forms\Components\TextInput::make('lab_test_cost')
                            ->label('Lab Test Cost (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => $set('total_cost', (float) ($get('lab_test_cost') ?? 0) + (float) ($get('drug_cost') ?? 0))
                            ),

                        TextInput::make('drug_cost')
                            ->label('Drug Cost (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->default(0)
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => $set('total_cost', (float) ($get('lab_test_cost') ?? 0) + (float) ($get('drug_cost') ?? 0))
                            ),

                        TextInput::make('total_cost')
                            ->label('Total Cost')
                            ->prefix('₦')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-calculated')
                            ->live()
                            ->default(fn (Get $get) => number_format(
                                (float) ($get('lab_test_cost') ?? 0) + (float) ($get('drug_cost') ?? 0),
                                2
                            )
                            ),

                        DatePicker::make('prescription_date')
                            ->label('Prescription Date')
                            ->required()
                            ->default(now())
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->native(false)
                            ->closeOnDateSelection(),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        Textarea::make('note')
                            ->label('Clinical Notes & Dosage Instructions')
                            ->rows(4)
                            ->disabled(fn ($record) => $record?->isTreated())
                            ->placeholder('Enter dosage instructions, frequency, duration, or additional observations...')
                            ->columnSpanFull(),
                    ]),

                Hidden::make('user_id')
                    ->default(fn () => auth()->id()),
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
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->colors([
                        'info' => Orphan::class,
                        'warning' => Widow::class,
                    ]),

                Tables\Columns\TextColumn::make('illnessModel.name')
                    ->label('Illness')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('doctor_name')
                    ->label('Doctor/Hospital')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('total_cost')
                    ->money('NGN')
                    ->state(fn (Prescription $record) => $record->total_cost)
                    ->sortable(),

                Tables\Columns\TextColumn::make('prescription_date')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('treated_at')
                    ->label('Treated At')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Prescribed By')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Treatment Status')
                    ->options(PrescriptionStatus::class),

                Tables\Filters\SelectFilter::make('prescribable_type')
                    ->label('Patient Type')
                    ->options([
                        Orphan::class => 'Orphan',
                        Widow::class => 'Widow',
                    ]),

                Tables\Filters\Filter::make('my_zone')
                    ->label('My Zone Only')
                    ->query(function (Builder $query) {
                        $zoneId = auth()->user()?->coordinatedZone?->id;
                        if (! $zoneId) {
                            return;
                        }

                        $query->where(function (Builder $q) use ($zoneId) {
                            $q->whereHas('prescribable', function (Builder $q2) use ($zoneId) {
                                $q2->where(function (Builder $q3) use ($zoneId) {
                                    $q3->where('prescribable_type', Orphan::class)
                                        ->whereHas('deceased', fn ($q4) => $q4->where('zone_id', $zoneId));
                                })->orWhere(function (Builder $q3) use ($zoneId) {
                                    $q3->where('prescribable_type', Widow::class)
                                        ->whereHas('deceased', fn ($q4) => $q4->where('zone_id', $zoneId));
                                });
                            });
                        });
                    })
                    ->default(),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn ($q) => $q->whereMonth('prescription_date', now()->month)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => $record->isPending() && $record->created_at->diffInDays(now()) <= 7),
                Action::make('preview_pdf')
                    ->label('Preview PDF')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Prescription $record): string => route('prescriptions.preview', ['prescription' => $record]))
                    ->openUrlInNewTab(),
                Action::make('download_pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Prescription $record): string => route('prescriptions.download', ['prescription' => $record]))
                    ->openUrlInNewTab(),
                Action::make('referral_pdf')
                    ->label('Referral Form')
                    ->icon('heroicon-o-document-text')
                    ->color('warning')
                    ->url(fn (Prescription $record): string => route('prescriptions.referral.preview', ['prescription' => $record]))
                    ->openUrlInNewTab(),
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
