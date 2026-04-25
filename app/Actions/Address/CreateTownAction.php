<?php

namespace App\Actions\Address;

use App\Data\Address\TownData;
use App\Models\City;
use App\Models\Town;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CreateTownAction
{
    public function execute(TownData $data): Town
    {
        // Optional: Verify City exists
        if (!City::where('id', $data->city_id)->exists()) {
            throw new ModelNotFoundException('The specified City does not exist.');
        }

        return Town::create([
            'name' => $data->name,
            'city_id' => $data->city_id
        ]);
    }
}
