<?php
// app/Filament/Widgets/EducationInterventionWidget.php

namespace App\Filament\Widgets;

use App\Models\InterventionRequest;
use App\Models\Orphan;
use App\Models\OrphanEducation;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class EducationInterventionWidget extends BaseWidget
{
    protected static ?string $heading = 'Education Interventions';
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Orphan::query()
                    ->whereHas('interventionRequests', fn(Builder $q) =>
                    $q->whereHas('type', fn($q) => $q->where('name', 'like', '%education%'))
                    )
                    ->withCount(['interventionRequests as education_requests' => fn(Builder $q) =>
                    $q->whereHas('type', fn($q) => $q->where('name', 'like', '%education%'))
                    ])
                    ->with(['educations' => fn($q) => $q->where('is_current', true)])
            )
            ->heading('Education Support Beneficiaries')
            ->description(fn() => 'Total education requests: ' .
                InterventionRequest::whereHas('type', fn($q) => $q->where('name', 'like', '%education%'))->count() .
                ' | Currently supported: ' .
                OrphanEducation::where('is_fee_supported', true)->where('is_current', true)->count())
            ->columns([
                TextColumn::make('full_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reg_no')
                    ->searchable(),

                TextColumn::make('educations.level')
                    ->label('Current Level')
                    ->formatStateUsing(fn($record) => $record->educations->first()?->level ?? 'N/A'),

                TextColumn::make('educations.institution.name')
                    ->label('Institution')
                    ->formatStateUsing(fn($record) => $record->educations->first()?->institution?->name ?? 'N/A'),

                TextColumn::make('education_requests')
                    ->label('Education Requests')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('educations.support_amount')
                    ->label('Support Amount (₦)')
                    ->formatStateUsing(fn($record) =>
                    number_format($record->educations->first()?->support_amount ?? 0, 2)
                    ),

                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->sortable(),
            ])
            ->defaultSort('education_requests', 'desc')
            ->paginated([5, 10, 25]);
    }
}
