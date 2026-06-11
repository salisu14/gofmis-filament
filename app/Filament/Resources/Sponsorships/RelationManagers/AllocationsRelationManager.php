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
use Filament\Infolists\Components\TextEntry;
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
                    ->compact() // Reduces internal padding/spacing
                    ->columns(2)
                    ->schema([
                        Select::make('orphan_education_id')
                            ->label('Education Enrollment')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->options(function (RelationManager $livewire): array {
                                /** @var Sponsorship $sponsorship */
                                $sponsorship = $livewire->getOwnerRecord();

                                return OrphanEducation::query()
                                    ->where('orphan_id', $sponsorship->orphan_id)
                                    ->with('institution')
                                    ->get()
                                    ->mapWithKeys(fn($edu) => [
                                        $edu->id => "{$edu->institution->name} — {$edu->orphanClass->name}"
                                    ])
                                    ->toArray();
                            }),

                        TextInput::make('amount_allocated')
                            ->label('Amount to Allocate')
                            ->required()
                            ->numeric()
                            ->prefix('₦')
                            ->minValue(1),

                        TextEntry::make('info')
                            ->state('Funds will be applied to the selected enrollment record.')
                            ->columnSpanFull()
                            ->extraAttributes(['class' => 'text-sm text-gray-500 italic']),
                    ]),
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
                    ->weight('bold')
                    ->placeholder('N/A'),

                TextColumn::make('education.level')
                    ->label('Level')
                    ->badge()
                    ->color('gray')
                    ->placeholder('N/A'),

                TextColumn::make('amount_allocated')
                    ->label('Amount Allocated')
                    ->money('NGN')
                    ->sortable()
                    ->alignment('right')
                    ->summarize(
                        Sum::make()
                            ->money('NGN')
                            ->label('Total Allocated')
                    ),

                TextColumn::make('created_at')
                    ->label('Allocated On')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('institution')
                    ->relationship('education.institution', 'name')
                    ->label('Filter by Institution')
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('New Allocation')
                    ->icon('heroicon-m-plus')
                    ->modalWidth('2xl')
                    ->modalHeading('Allocate Funds')
                    ->modalDescription('Allocate a portion of the sponsorship commitment to a specific educational enrollment.')
                    ->createAnother(false),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('2xl')
                    ->modalHeading('Edit Allocation'),

                DeleteAction::make()
                    ->modalHeading('Delete Allocation')
                    ->modalDescription('Are you sure you want to delete this allocation? The funds will be returned to the sponsorship balance.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Delete Selected Allocations'),
                ])->label('Bulk Actions'),
            ])
            ->emptyStateHeading('No allocations yet')
            ->emptyStateDescription('Start by allocating funds from this sponsorship to an educational enrollment.')
            ->emptyStateIcon('heroicon-o-banknotes')
            ->defaultSort('created_at', 'desc');
    }
}
