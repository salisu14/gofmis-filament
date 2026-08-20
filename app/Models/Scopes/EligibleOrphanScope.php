<?php

namespace App\Models\Scopes;

use App\Enums\Gender;
use App\Enums\OrphanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class EligibleOrphanScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $cutoffDate = now()->subYears(18)->format('Y-m-d');

        $builder
            ->where($model->qualifyColumn('is_eligible'), true)
            ->whereIn($model->qualifyColumn('status'), [OrphanStatus::ACTIVE->value, 'active', 'ACTIVE'])
            ->where(function ($query) use ($model, $cutoffDate) {
                $query->where(function ($q) use ($model, $cutoffDate) {
                    $q->whereIn($model->qualifyColumn('gender'), [Gender::MALE->value, 'MALE', 'male'])
                        ->where($model->qualifyColumn('birth_date'), '>', $cutoffDate);
                })->orWhere(function ($q) use ($model) {
                    $q->whereIn($model->qualifyColumn('gender'), [Gender::FEMALE->value, 'FEMALE', 'female'])
                        ->where($model->qualifyColumn('is_married'), false);
                });
            });
    }
}
