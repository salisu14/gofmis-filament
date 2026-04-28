<?php

namespace App\Filament\Resources\VocationalSkills\RelationManagers;

use App\Filament\Resources\VocationalSkills\VocationalSkillResource;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VocationalSkillRelationManager extends RelationManager
{
    /**
     * This manager is used inside the VocationalSkillResource
     * to show which orphans have this skill.
     */
    protected static string $relationship = 'orphanSkills';
    protected static ?string $relatedResource = VocationalSkillResource::class;

    protected static ?string $recordTitleAttribute = 'full_name';

    protected static ?string $title = 'Proficient Orphans';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('full_name')
                    ->label('Orphan Name')
                    ->disabled()
                    ->columnSpanFull(),

                TextInput::make('specify')
                    ->label('Specialization / Level')
                    ->placeholder('e.g. Advanced Embroidery, Furniture Making')
                    ->maxLength(255)
                    ->helperText('Specific proficiency details for this student.')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Student Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('reg_no')
                    ->label('Reg No')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('specify')
                    ->label('Specification')
                    ->placeholder('No specific details')
                    ->wrap(),
            ])
            ->filters([
                TernaryFilter::make('is_eligible')
                    ->label('Eligible Students Only'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Attach Student')
                    ->preloadRecordSelect()
                    ->schema(fn(AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('specify')
                            ->label('Specific Proficiency')
                            ->placeholder('e.g. Expert in tailoring'),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Edit Student Proficiency'),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
