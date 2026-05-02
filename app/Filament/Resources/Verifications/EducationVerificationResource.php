<?php

namespace App\Filament\Resources\Verifications;

use App\Filament\Resources\Verifications\Schemas\EducationVerificationForm;
use App\Filament\Resources\Verifications\Schemas\EducationVerificationInfolist;
use App\Filament\Resources\Verifications\Tables\EducationVerificationsTable;
use App\Models\InterventionRequest;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EducationVerificationResource extends Resource
{
    protected static ?string $model = InterventionRequest::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Education Verification';

    protected static ?string $slug = 'education-verification';
    protected static ?string $modelLabel = 'Education Request';
    protected static ?string $pluralModelLabel = 'Education Requests';
    protected static string|null|\UnitEnum $navigationGroup = 'Verifications';
    protected static ?int $navigationSort = 1;

    /**
     * ✅ Role-based access: Only education-verifier, admin, and super-admin can see this resource
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin', 'education-verifier']) ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // Verifiers cannot create requests
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin', 'education-verifier']) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole(['admin', 'super_admin']) ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'super_admin', 'education-verifier']) ?? false;
    }

    /**
     * ✅ Scoped query: Education-verifiers only see pending/under_review requests
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->whereHas('type', fn($q) => $q->where('name', 'like', '%education%'));

        if (auth()->user()?->hasRole('education-verifier')) {
            $query->whereIn('status', ['pending', 'under_review']);
        }

        return $query;
    }
    public static function form(Schema $schema): Schema
    {
        return EducationVerificationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EducationVerificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EducationVerificationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEducationVerifications::route('/'),
            'edit' => Pages\EditEducationVerification::route('/{record}/edit'),
            'view' => Pages\ViewEducationVerification::route('/{record}'),
        ];
    }
}
