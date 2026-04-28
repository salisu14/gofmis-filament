<?php
// app/Filament/Widgets/HealthcareInterventionWidget.php

namespace App\Filament\Widgets;

use App\Models\InterventionRequest;
use App\Models\Orphan;
use App\Models\Prescription;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HealthcareInterventionWidget extends BaseWidget
{
    protected static ?string $heading = 'Healthcare Interventions';
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Orphan::query()
                    ->where(function (Builder $query) {
                        $query->whereHas('interventionRequests', function ($q) {
                            $q->whereHas('type', function ($q) {
                                $q->where('name', 'like', '%health%');
                            });
                        })->orWhereHas('prescriptions');
                    })
                    ->withCount([
                        'interventionRequests as health_requests' => function ($q) {
                            $q->whereHas('type', function ($q) {
                                $q->where('name', 'like', '%health%');
                            });
                        }
                    ])
                    ->withCount('prescriptions')
                    ->addSelect([
                        'prescriptions_sum_total_cost' => Prescription::selectRaw(
                            'COALESCE(SUM(lab_test_cost + drug_cost), 0)'
                        )
                            ->whereColumn('prescriptions.prescribable_id', 'orphans.id')
                            ->where('prescriptions.prescribable_type', Orphan::class)
                    ])
            )
            ->heading('Healthcare & Medical Beneficiaries')
            ->description(function () {
                return 'Medical requests: ' .
                    InterventionRequest::whereHas('type', function ($q) {
                        $q->where('name', 'like', '%health%');
                    })->count() .
                    ' | Prescriptions: ' . Prescription::count() .
                    ' | Total prescription cost: ₦' . number_format(
                        Prescription::sum(
                            DB::raw('COALESCE(lab_test_cost, 0) + COALESCE(drug_cost, 0)')
                        )
                    );
            })
            ->columns([
                TextColumn::make('full_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('reg_no')
                    ->searchable(),

                TextColumn::make('health_requests')
                    ->label('Health Requests')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('prescriptions_count')
                    ->label('Prescriptions')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('prescriptions_sum_total_cost')
                    ->label('Prescription Cost (₦)')
                    ->numeric(2)
                    ->money('NGN'),

                TextColumn::make('zone.name')
                    ->label('Zone')
                    ->sortable(),
            ])
            ->defaultSort('prescriptions_sum_total_cost', 'desc')
            ->paginated([5, 10, 25]);
    }
}
