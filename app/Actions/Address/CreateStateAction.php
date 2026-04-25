<?php

namespace App\Actions\Address;

use App\Data\Address\StateData;
use App\Models\State;

class CreateStateAction
{
    public function execute(StateData $data): State
    {
        return State::create([
            'name' => $data->name
        ]);
    }
}
