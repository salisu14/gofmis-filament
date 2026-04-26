<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Enums\Gender;

class EligibleOrphanScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(function ($query) {
            $query->where(function ($q) {
                $q->where('gender', Gender::MALE)
                    ->where('birth_date', '>=', now()->subYears(18));
            })->orWhere(function ($q) {
                $q->where('gender', Gender::FEMALE)
                    ->where('is_married', false);
            });
        });
    }
}
