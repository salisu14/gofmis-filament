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

        $zoneId = $user->zone_id;
        $isAdmin = $user->hasAnyRole(['admin', 'super-admin']);

        // Admin sees everything
        if ($isAdmin || !$zoneId) {
            return $query;
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
        return auth()->user()?->hasAnyRole(['coordinator', 'admin', 'super-admin']) ?? false;
    }

    public static function canEdit($record): bool
    {
        $user = auth()->user();

        if (!$user) return false;

        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        return static::getRecordZoneId($record) === $user->zone_id;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super-admin']) ?? false;
    }

    abstract protected static function getRecordZoneId($record): ?string;
}
