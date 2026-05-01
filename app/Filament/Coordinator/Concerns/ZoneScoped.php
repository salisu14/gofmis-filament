<?php

namespace App\Filament\Coordinator\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ZoneScoped
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if (!$user) {
            return $query;
        }

        $isAdmin = $user->hasAnyRole(['admin', 'super-admin']);

        // Admin sees everything
        if ($isAdmin) {
            return $query;
        }

        // Get zone via relationship
        $zoneId = $user->coordinatedZone?->id;

        // If coordinator has no zone → block access (or return empty)
        if (!$zoneId) {
            return $query->whereRaw('1 = 0'); // safer than abort in query context
        }

        return static::applyZoneScope($query, $zoneId);
    }

    abstract protected static function applyZoneScope(Builder $query, string $zoneId): Builder;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['coordinator', 'admin', 'super-admin']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['coordinator', 'admin', 'super_admin']) ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (!$user) return false;

        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return true;
        }

        $zoneId = $user->coordinatedZone?->id;

        if (!$zoneId) return false;

        return static::getRecordZoneId($record) === $zoneId;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin']) ?? false;
    }

    abstract protected static function getRecordZoneId($record): ?string;
}
