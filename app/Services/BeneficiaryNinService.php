<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BeneficiaryNinService
{
    public function validateForSave(Model $model): void
    {
        if ($model->has_nin === null) {
            $model->has_nin = filled($model->nin) && (bool) preg_match('/^[0-9]{11}$/D', (string) $model->nin);
        } else {
            $model->has_nin = (bool) $model->has_nin;
        }

        if (! $model->has_nin) {
            $model->nin = null;

            return;
        }

        $unique = Rule::unique($model->getTable(), 'nin');
        if ($model->exists) {
            $unique->ignore($model->getKey(), $model->getKeyName());
        }

        Validator::make(['nin' => $model->nin], [
            'nin' => ['required', 'string', 'size:11', 'regex:/^[0-9]{11}$/D', $unique],
        ])->validate();
    }
}
