<?php

namespace App\Filament\Coordinator\Resources\ProjectResource\Pages;
use App\Filament\Coordinator\Resources\ProjectResource;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn($record) => in_array($record->status->value, ['planning', 'approved'])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Project Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('type')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state->label()),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn($state) => $state->color()),
                        TextEntry::make('budget_allocated')
                            ->money('NGN'),
                        TextEntry::make('progress_percentage')
                            ->suffix('%'),
                    ]),
            ]);
    }
}
