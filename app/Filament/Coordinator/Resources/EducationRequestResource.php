<?php

// app/Filament/Coordinator/Resources/EducationRequestResource.php

namespace App\Filament\Coordinator\Resources;

use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest;
use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\EditEducationRequest;
use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\ListEducationRequests;
use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\ViewEducationRequest;
use App\Models\InterventionRequest;
use App\Models\InterventionType;
use App\Models\Item;
use App\Models\Orphan;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

class EducationRequestResource extends Resource
{
    protected static ?string $model = InterventionRequest::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Education Requests';

    protected static ?string $modelLabel = 'Education Request';

    protected static ?string $pluralModelLabel = 'Education Requests';

    protected static string|null|\UnitEnum $navigationGroup = 'Intervention Requests';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        // ✅ FIXED: Use coordinatedZone instead of zone_id
        $zoneId = auth()->user()?->coordinatedZone?->id;
        $isAdmin = auth()->user()?->hasAnyRole(['admin', 'super_admin']);

        $query = parent::getEloquentQuery()
            ->education();

        if ($isAdmin) {
            return $query;
        }

        if (! $zoneId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('orphan', fn (Builder $q) => $q->whereHas('deceased', fn ($q2) => $q2->where('zone_id', $zoneId)
        ));
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

        return $user->managesZone($record->orphan?->deceased?->zone_id);
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if ($user?->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        // ✅ FIXED: Use coordinatedZone for zone comparison
        $zoneId = $user?->coordinatedZone?->id;

        return $record->status === 'pending' &&
            $user?->managesZone($record->orphan?->deceased?->zone_id);
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        $user = auth()->user();
        // ✅ FIXED: Use coordinatedZone instead of zone_id
        $zoneId = $user?->coordinatedZone?->id;

        return $schema
            ->schema([
                Section::make('Orphan Selection')
                    ->schema([
                        Select::make('orphan_id')
                            ->label('Orphan')
                            ->relationship(
                                'orphan',
                                'full_name',
                                function (Builder|Relation|null $query) {
                                    if (! $query) {
                                        return null;
                                    }

                                    $user = auth()->user();
                                    $zoneId = $user?->coordinatedZone?->id;

                                    if (! $user?->hasAnyRole(['admin', 'super_admin'])) {
                                        if (! $zoneId) {
                                            return $query->whereRaw('1 = 0');
                                        }

                                        return $query
                                            ->whereHas('deceased', fn ($q) => $q->where('zone_id', $zoneId))
                                            ->where('is_eligible', true);
                                    }

                                    return $query->where('is_eligible', true);
                                }
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Search and select an eligible orphan in your zone.'),

                        Placeholder::make('current_education_summary')
                            ->label('Current Education Context')
                            ->content(function ($get) {
                                $orphanId = $get('orphan_id');
                                if (is_array($orphanId)) {
                                    $orphanId = reset($orphanId);
                                }
                                if (! $orphanId || ! is_string($orphanId)) {
                                    return 'Select an orphan to view education record.';
                                }

                                $user = auth()->user();
                                $zoneId = $user?->coordinatedZone?->id;

                                $orphan = Orphan::find($orphanId);
                                if (! $orphan) {
                                    return 'Select an orphan to view education record.';
                                }

                                if (! $user?->hasAnyRole(['admin', 'super_admin']) && $orphan->deceased?->zone_id !== $zoneId) {
                                    return 'Unauthorized: Orphan outside assigned zone.';
                                }

                                $education = \App\Models\OrphanEducation::with(['institution', 'orphanClass'])
                                    ->where('orphan_id', $orphanId)
                                    ->where('is_current', true)
                                    ->latest()
                                    ->first() ?? \App\Models\OrphanEducation::with(['institution', 'orphanClass'])
                                    ->where('orphan_id', $orphanId)
                                    ->latest()
                                    ->first();

                                if (! $education) {
                                    return 'No school enrollment record found for this orphan.';
                                }

                                $institutionName = $education->institution?->name ?? 'N/A';
                                $level = $education->level;
                                $fee = $education->school_fee ? '₦'.number_format($education->school_fee, 2) : 'N/A';
                                $freq = ucfirst($education->fee_frequency ?? 'termly');
                                $supported = $education->is_fee_supported ? 'Yes (₦'.number_format($education->support_amount, 2).')' : 'No';

                                return "School: {$institutionName} | Level: {$level} | Fee: {$fee} ({$freq}) | Fee Supported: {$supported}";
                            }),
                    ]),

                Section::make('Request Details')
                    ->columns(2)
                    ->schema([
                        Select::make('intervention_type_id')
                            ->label('Education Support Type')
                            ->options(function () {
                                $getOptions = fn () => \App\Models\InterventionType::query()
                                    ->where(function ($q) {
                                        $q->whereRaw('LOWER(name) LIKE ?', ['%education%'])
                                            ->orWhereRaw('LOWER(name) LIKE ?', ['%school%'])
                                            ->orWhereRaw('LOWER(name) LIKE ?', ['%tuition%'])
                                            ->orWhereRaw('LOWER(name) LIKE ?', ['%uniform%'])
                                            ->orWhereRaw('LOWER(name) LIKE ?', ['%book%'])
                                            ->orWhereRaw('LOWER(name) LIKE ?', ['%scholarship%']);
                                    })
                                    ->pluck('name', 'id')
                                    ->toArray();

                                $options = $getOptions();
                                if (empty($options)) {
                                    (new \Database\Seeders\InterventionTypeSeeder)->run();
                                    $options = $getOptions();
                                }

                                return $options;
                            })
                            ->required()
                            ->searchable()
                            ->preload()
                            ->dehydrateStateUsing(fn ($state) => is_array($state) ? (reset($state) ?: null) : $state)
                            ->exists('intervention_types', 'id'),

                        DatePicker::make('request_date')
                            ->label('Request Date')
                            ->required()
                            ->default(now()),

                        Select::make('requested_level')
                            ->label('Requested Level/Class')
                            ->options([
                                'primary_1' => 'Primary 1',
                                'primary_2' => 'Primary 2',
                                'primary_3' => 'Primary 3',
                                'primary_4' => 'Primary 4',
                                'primary_5' => 'Primary 5',
                                'primary_6' => 'Primary 6',
                                'jss_1' => 'JSS 1',
                                'jss_2' => 'JSS 2',
                                'jss_3' => 'JSS 3',
                                'sss_1' => 'SSS 1',
                                'sss_2' => 'SSS 2',
                                'sss_3' => 'SSS 3',
                                'tertiary' => 'Tertiary',
                            ])
                            ->placeholder('Select level if applicable'),

                        TextInput::make('requested_amount')
                            ->label('Requested Amount (₦)')
                            ->numeric()
                            ->prefix('₦')
                            ->placeholder('If fee support requested'),
                    ]),

                Section::make('Requested Items')
                    ->description('Specify physical items required for this education request.')
                    ->icon('heroicon-m-shopping-bag')
                    ->visible(function ($get) {
                        $typeId = $get('intervention_type_id');
                        if (is_array($typeId)) {
                            $typeId = reset($typeId);
                        }
                        if (! $typeId) {
                            return true;
                        }

                        $type = InterventionType::find($typeId);
                        if (! $type) {
                            return true;
                        }

                        return $type->supportsItems() || $type->requiresItems();
                    })
                    ->schema([
                        Repeater::make('items')
                            ->relationship('items')
                            ->disabled(fn ($record) => $record && $record->status !== 'pending')
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('item_id')
                                        ->label('Item')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->options(function () {
                                            return Item::with('category')->get()
                                                ->groupBy(fn ($item) => $item->category?->name ?? 'General Education Items')
                                                ->map(fn ($items) => $items->pluck('name', 'id'))
                                                ->toArray();
                                        })
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set) {
                                            if ($state) {
                                                $item = Item::find($state);
                                                $set('item_name', $item?->name);
                                                if ($item?->description) {
                                                    $set('specification', $item->description);
                                                }
                                            }
                                        }),

                                    TextInput::make('quantity_requested')
                                        ->label('Qty Requested')
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(1)
                                        ->required(),
                                ]),

                                Grid::make(2)->schema([
                                    TextInput::make('orphan_class')
                                        ->label('Size / Class Context')
                                        ->placeholder('e.g. Large, Size 34, JSS 1'),

                                    TextInput::make('specification')
                                        ->label('Specific Details')
                                        ->placeholder('e.g. Blue color, Cotton material'),
                                ]),

                                Hidden::make('item_name'),
                            ])
                            ->minItems(function ($get) {
                                $typeId = $get('intervention_type_id');
                                if (is_array($typeId)) {
                                    $typeId = reset($typeId);
                                }
                                if (! $typeId) {
                                    return 0;
                                }

                                $type = InterventionType::find($typeId);

                                return ($type && $type->requiresItems()) ? 1 : 0;
                            })
                            ->addActionLabel('Add Requested Item')
                            ->collapsible()
                            ->defaultItems(0)
                            ->itemLabel(fn (array $state): ?string => ! empty($state['item_name'])
                                ? $state['item_name'].(isset($state['quantity_requested']) ? " (Qty: {$state['quantity_requested']})" : '')
                                : (! empty($state['item_id']) ? (Item::find($state['item_id'])?->name ?? 'Requested Item') : 'Requested Item')
                            ),
                    ]),

                Section::make('Justification')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Reason for Request')
                            ->required()
                            ->rows(4)
                            ->placeholder('Explain why education support is needed...'),

                        FileUpload::make('supporting_documents')
                            ->label('Supporting Documents')
                            ->multiple()
                            ->directory('education-requests')
                            ->acceptedFileTypes(['application/pdf', 'image/*']),
                    ]),

                Hidden::make('status')
                    ->default('pending'),

                Hidden::make('verification_status')
                    ->default('pending'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('orphan.full_name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('orphan.deceased.zone.name')
                    ->label('Zone')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('type.name')
                    ->label('Support Type')
                    ->badge(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'under_review',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'gray' => 'completed',
                    ]),

                Tables\Columns\TextColumn::make('verification_status')
                    ->badge()
                    ->label('Verification')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'in_progress',
                        'success' => 'verified',
                        'danger' => 'failed',
                    ]),

                Tables\Columns\TextColumn::make('request_date')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'completed' => 'Completed',
                    ]),

                // ✅ FIXED: Use coordinatedZone instead of zone_id
                Tables\Filters\Filter::make('my_zone')
                    ->label('My Zone Only')
                    ->query(function (Builder $query) {
                        $zoneId = auth()->user()?->coordinatedZone?->id;
                        if ($zoneId) {
                            $query->whereHas('orphan.deceased', fn ($q) => $q->where('zone_id', $zoneId));
                        }
                    })
                    ->default(),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn ($q) => $q->whereMonth('request_date', now()->month)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),
            ])
            ->defaultSort('request_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEducationRequests::route('/'),
            'create' => CreateEducationRequest::route('/create'),
            'edit' => EditEducationRequest::route('/{record}/edit'),
            'view' => ViewEducationRequest::route('/{record}'),
        ];
    }
}
