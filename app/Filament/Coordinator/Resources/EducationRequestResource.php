<?php
// app/Filament/Coordinator/Resources/EducationRequestResource.php

namespace App\Filament\Coordinator\Resources;

use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\CreateEducationRequest;
use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\EditEducationRequest;
use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\ListEducationRequests;
use App\Filament\Coordinator\Resources\EducationRequestResource\Pages\ViewEducationRequest;
use App\Models\InterventionRequest;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
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
        $isAdmin = auth()->user()?->hasRole(['admin', 'super_admin']);

        $query = parent::getEloquentQuery()
            ->whereHas('type', fn($q) => $q->where('name', 'like', '%education%'));

        if ($isAdmin || !$zoneId) {
            return $query;
        }

        return $query->whereHas('orphan', fn(Builder $q) => $q->whereHas('deceased', fn($q2) =>
        $q2->where('zone_id', $zoneId)
        ));
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['coordinator', 'admin', 'super_admin']) ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if ($user->hasRole(['admin', 'super_admin'])) return true;

        // ✅ FIXED: Use coordinatedZone for zone comparison
        $zoneId = $user?->coordinatedZone?->id;

        return $record->status === 'pending' &&
            $record->orphan?->deceased?->zone_id === $zoneId;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole(['admin', 'super_admin']) ?? false;
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
                                function (Builder|Relation|null $query) use ($zoneId) {
                                    if (!$query) {
                                        return null;
                                    }

                                    return $query
                                        ->whereHas('deceased', fn($q) => $q->where('zone_id', $zoneId))
                                        ->where('is_eligible', true);
                                }
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Search and select an eligible orphan in your zone.'),
                    ]),

                Section::make('Request Details')
                    ->columns(2)
                    ->schema([
                        Select::make('intervention_type_id')
                            ->label('Education Support Type')
                            ->options(fn() => \App\Models\InterventionType::where('name', 'like', '%education%')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

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
                            $query->whereHas('orphan.deceased', fn($q) => $q->where('zone_id', $zoneId));
                        }
                    })
                    ->default(),

                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn($q) => $q->whereMonth('request_date', now()->month)),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn($record) => $record->status === 'pending'),
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
