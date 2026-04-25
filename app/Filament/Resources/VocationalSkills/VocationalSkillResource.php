<?php

namespace App\Filament\Resources\VocationalSkills;

use App\Filament\Resources\VocationalSkills\Pages\CreateVocationalSkill;
use App\Filament\Resources\VocationalSkills\Pages\EditVocationalSkill;
use App\Filament\Resources\VocationalSkills\Pages\ListVocationalSkills;
use App\Filament\Resources\VocationalSkills\Schemas\VocationalSkillForm;
use App\Filament\Resources\VocationalSkills\Tables\VocationalSkillsTable;
use App\Models\VocationalSkill;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VocationalSkillResource extends Resource
{
    protected static ?string $model = VocationalSkill::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return VocationalSkillForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VocationalSkillsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VocationalSkillRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVocationalSkills::route('/'),
            'create' => CreateVocationalSkill::route('/create'),
            'edit' => EditVocationalSkill::route('/{record}/edit'),
        ];
    }
}
