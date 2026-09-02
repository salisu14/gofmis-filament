<?php

namespace App\Filament\Coordinator\Resources;

use App\Filament\Coordinator\Resources\OrphanHistoryResource\Pages\ListOrphanHistories;
use App\Models\Orphan;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrphanHistoryResource extends Resource
{
    use \App\Filament\Coordinator\Concerns\ZoneScoped;

    protected static ?string $model = Orphan::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-archive-box';

    protected static string|null|\UnitEnum $navigationGroup = 'Beneficiary Registration';

    protected static ?string $navigationLabel = 'Orphan History';

    protected static ?string $modelLabel = 'Orphan History';

    protected static ?string $pluralModelLabel = 'Orphan History';

    protected static ?int $navigationSort = 50;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'gray';
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->can('view_orphans');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                \App\Models\Scopes\EligibleOrphanScope::class,
            ])
            ->historical();
    }

    protected static function applyZoneScope(Builder $query, string $zoneId): Builder
    {
        return $query->whereHas('deceased', fn (Builder $q) => $q->where('zone_id', $zoneId));
    }

    protected static function getRecordZoneId($record): ?string
    {
        return $record->deceased?->zone_id;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name', 'middle_name'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('reg_no')
                    ->label('Reg No')
                    ->searchable()
                    ->badge()
                    ->sortable(),

                TextColumn::make('gender')
                    ->badge()
                    ->sortable(),

                TextColumn::make('age')
                    ->label('Age')
                    ->state(fn ($record) => $record->birth_date?->age)
                    ->sortable('birth_date')
                    ->alignCenter(),

                TextColumn::make('deceased.full_name')
                    ->label('Deceased Household')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->badge('success')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('archive_reason')
                    ->label('Archive / Exit Reason')
                    ->state(function (Orphan $record): string {
                        if ($record->rejection_reason) {
                            return $record->rejection_reason;
                        }
                        if ($record->birth_date?->age >= 18) {
                            return 'Archived: Overaged (18+)';
                        }
                        if ($record->is_married) {
                            return 'Archived: Married';
                        }

                        return 'Historical Record';
                    })
                    ->wrap(),

                TextColumn::make('sponsorship_status')
                    ->label('Sponsorship')
                    ->state(fn (Orphan $record): string => $record->hasActiveSponsorship() ? 'Sponsored' : 'Not Sponsored')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sponsored' ? 'success' : 'gray'),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (Orphan $record): string => OrphanResource::getUrl('view', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrphanHistories::route('/'),
        ];
    }
}
