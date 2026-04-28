<?php

namespace App\Filament\Resources\Sponsorships\RelationManagers;

use App\Filament\Resources\Sponsorships\SponsorshipResource;
use App\Models\OrphanEducation;
use App\Models\Sponsorship;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';
    protected static ?string $relatedResource = SponsorshipResource::class;

    protected static ?string $title = 'Fund Allocations';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Allocation Details')
                    ->description('Direct sponsorship funds to specific educational enrollments.')
                    ->schema([
                        Select::make('orphan_education_id')
                            ->label('Education Enrollment')
                            ->placeholder('Select an enrollment')
                            ->required()
                            ->options(function (RelationManager $livewire): array {
                                /** @var Sponsorship $sponsorship */
                                $sponsorship = $livewire->getOwnerRecord();

                                // Only allow allocations to the sponsored orphan's education records
                                return OrphanEducation::query()
                                    ->where('orphan_id', $sponsorship->orphan_id)
                                    ->with('institution')
                                    ->get()
                                    ->mapWithKeys(fn($edu) => [
                                        $edu->id => "{$edu->institution->name} — {$edu->level} (" . ($edu->institution->type->value ?? 'N/A') . ")"
                                    ])
                                    ->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->hint('Only enrollments for this sponsored orphan are shown.'),

                        TextInput::make('amount_allocated')
                            ->label('Amount to Allocate')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->minValue(1)
                            ->helperText('The amount deducted from the total commitment for this specific school/period.'),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('education.institution.name')
                    ->label('Institution')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('education.level')
                    ->label('Level')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('amount_allocated')
                    ->label('Amount')
                    ->money('NGN')
                    ->sortable()
                    ->summarize(Sum::make()->money('NGN')->label('Total Allocated')),

                TextColumn::make('created_at')
                    ->label('Allocated On')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('institution')
                    ->relationship('education.institution', 'name')
                    ->label('Filter by Institution'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Allocation')
                    ->icon('heroicon-m-plus')
                    ->modalWidth('2xl')
                    ->modalHeading('Allocate Funds'),
            ])
            ->recordActions([
                EditAction::make()->modalWidth('2xl'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
