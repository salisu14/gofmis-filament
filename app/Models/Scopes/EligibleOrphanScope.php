<?php

namespace App\Models\Scopes;

use App\Enums\Gender;
use App\Models\Orphan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class EligibleOrphanScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder
            ->where($model->qualifyColumn('is_eligible'), true)
            ->where($model->qualifyColumn('status'), '!=', Orphan::STATUS_ARCHIVED)
            ->where(function ($query) use ($model) {
                $query->where(function ($q) use ($model) {
                    $q->where($model->qualifyColumn('gender'), Gender::MALE)
                        ->whereDate($model->qualifyColumn('birth_date'), '>', now()->subYears(18)->toDateString());
                })->orWhere(function ($q) use ($model) {
                    $q->where($model->qualifyColumn('gender'), Gender::FEMALE)
                        ->where($model->qualifyColumn('is_married'), false);
                });
            });
    }
}
