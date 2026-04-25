<?php

namespace App\Actions\Zone;

use App\Data\Zone\ZoneData;
use App\Models\Zone;

class CreateZoneAction
{
    public function execute(ZoneData $data): Zone
    {
        return Zone::create([
            'name' => $data->name,
            'address' => $data->address,
            'city' => $data->city,
            'state' => $data->state,
        ]);
    }
}
