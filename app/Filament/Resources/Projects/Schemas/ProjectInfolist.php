<?php

namespace App\Filament\Resources\Projects\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Project Information')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')
                            ->weight('bold'),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(function ($state) {
                                // If it's already an Enum instance, just call the label method
                                if ($state instanceof \App\Enums\ProjectType) {
                                    return $state->label();
                                }

                                // If it's a string/int (from Livewire updates), convert it first
                                return \App\Enums\ProjectType::tryFrom($state)?->label() ?? $state;
                            }),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn($state) => $state->color()),
                        TextEntry::make('zone.name'),
                        TextEntry::make('coordinator.name')
                            ->placeholder('Unassigned'),
                        TextEntry::make('deceased.full_name')
                            ->label('Beneficiary Family')
                            ->placeholder('Community Project'),
                    ]),

                Section::make('Budget Overview')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('budget_allocated')
                            ->label('Allocated')
                            ->money('NGN'),
                        TextEntry::make('budget_spent')
                            ->label('Spent')
                            ->money('NGN'),
                        TextEntry::make('budget_remaining')
                            ->label('Remaining')
                            ->money('NGN')
                            ->color(fn($state) => $state < 0 ? 'danger' : 'success'),
                    ]),

                Section::make('Timeline')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('start_date')
                            ->date('M d, Y'),
                        TextEntry::make('expected_completion_date')
                            ->date('M d, Y'),
                        TextEntry::make('actual_completion_date')
                            ->date('M d, Y')
                            ->placeholder('Not completed'),
                    ]),

                Section::make('Progress')
                    ->schema([
                        TextEntry::make('progress_percentage')
                            ->label('Completion')
                            ->suffix('%')
                            ->formatStateUsing(fn($state) => "{$state}%"),
                    ]),

                Section::make('Description')
                    ->schema([
                        TextEntry::make('description')
                            ->html()
                            ->prose(),
                    ]),
            ]);
    }
}
