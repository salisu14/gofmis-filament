<?php

namespace App\Filament\Resources\CompanyInformation;

use App\Filament\Resources\CompanyInformation\Pages\CreateCompanyInformation;
use App\Filament\Resources\CompanyInformation\Pages\EditCompanyInformation;
use App\Filament\Resources\CompanyInformation\Pages\ListCompanyInformation;
use App\Filament\Resources\CompanyInformation\Pages\ViewCompanyInformation;
use App\Filament\Resources\CompanyInformation\Schemas\CompanyInformationForm;
use App\Filament\Resources\CompanyInformation\Schemas\CompanyInformationInfolist;
use App\Filament\Resources\CompanyInformation\Tables\CompanyInformationTable;
use App\Models\CompanyInformation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CompanyInformationResource extends Resource
{
    protected static ?string $model = CompanyInformation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingOffice2;

    protected static ?string $recordTitleAttribute = 'company_name';

    protected static ?string $navigationLabel = 'Company Information';

    protected static string|null|\UnitEnum $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function canDelete($model): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CompanyInformationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CompanyInformationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CompanyInformationTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanyInformation::route('/'),
            'create' => CreateCompanyInformation::route('/create'),
            'view' => ViewCompanyInformation::route('/{record}'),
            'edit' => EditCompanyInformation::route('/{record}/edit'),
        ];
    }
}
