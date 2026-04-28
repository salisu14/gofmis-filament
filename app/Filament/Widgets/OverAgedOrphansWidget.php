<?php
// app/Filament/Widgets/OverAgedOrphansWidget.php

namespace App\Filament\Widgets;

use App\Enums\Gender;
use App\Models\Orphan;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class OverAgedOrphansWidget extends BaseWidget
{
    protected static ?string $heading = 'Over-Aged & Married Orphans';
    protected static ?int $sort = 9;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Orphan::query()
                    ->where(function ($query) {
                        $query->where(function ($q) {
                            // Over-aged males (>= 18)
                            $q->where('gender', Gender::MALE)
                                ->where('age', '>=', 18)
                                ->where('is_eligible', true);
                        })->orWhere(function ($q) {
                            // Married girls (female, married)
                            $q->where('gender', Gender::FEMALE)
                                ->where('is_married', true);
                        });
                    })
                    ->with(['deceased.zone', 'educations' => fn($q) => $q->where('is_current', true)])
            )
            ->heading('Over-Aged Male Orphans & Married Girls')
            ->description(fn() => 'Over-aged males (≥18): ' .
                Orphan::where('gender', Gender::MALE)->where('age', '>=', 18)->where('is_eligible', true)->count() .
                ' | Married girls: ' .
                Orphan::where('gender', Gender::FEMALE)->where('is_married', true)->count())
            ->columns([
                TextColumn::make('full_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reg_no')
                    ->searchable(),

                TextColumn::make('gender')
                    ->badge()
                    ->colors([
                        'info' => Gender::MALE->value,
                        'danger' => Gender::FEMALE->value,
                    ]),

                TextColumn::make('age')
                    ->numeric()
                    ->sortable()
                    ->color(fn($record) => $record->age >= 18 ? 'danger' : 'success'),

                IconColumn::make('is_married')
                    ->label('Married')
                    ->boolean(),

                TextColumn::make('married_at')
                    ->date('M d, Y')
                    ->placeholder('Not married')
                    ->toggleable(),

                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        if ($record->gender === Gender::MALE && $record->age >= 18) {
                            return 'Over-Aged Male';
                        }
                        if ($record->gender === Gender::FEMALE && $record->is_married) {
                            return 'Married Girl';
                        }
                        return 'Other';
                    })
                    ->colors([
                        'danger' => 'Over-Aged Male',
                        'warning' => 'Married Girl',
                    ]),

                TextColumn::make('educations.level')
                    ->label('Education')
                    ->formatStateUsing(fn($record) => $record->educations->first()?->level ?? 'N/A'),

                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'over_aged_male' => 'Over-Aged Male (≥18)',
                        'married_girl' => 'Married Girl',
                    ])
                    ->query(function ($query, $data) {
                        if ($data['value'] === 'over_aged_male') {
                            $query->where('gender', Gender::MALE)->where('age', '>=', 18);
                        } elseif ($data['value'] === 'married_girl') {
                            $query->where('gender', Gender::FEMALE)->where('is_married', true);
                        }
                    }),
            ])
            ->defaultSort('age', 'desc')
            ->paginated([10, 25, 50]);
    }
}
