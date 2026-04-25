<?php

namespace App\Actions\Address;

use App\Data\Address\CityData;
use App\Models\City;
use App\Models\State;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CreateCityAction
{
    public function execute(CityData $data): City
    {
        // Optional: Verify State exists before creating City
        if (!State::where('id', $data->state_id)->exists()) {
            throw new ModelNotFoundException('The specified State does not exist.');
        }

        return City::create([
            'name' => $data->name,
            'state_id' => $data->state_id
        ]);
    }
}
