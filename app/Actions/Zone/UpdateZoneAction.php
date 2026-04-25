<?php

namespace App\Actions\Zone;

use App\Data\Zone\ZoneData;
use App\Models\Zone;

class UpdateZoneAction
{
    public function execute(Zone $zone, ZoneData $data): Zone
    {
        $zone->update([
            'name' => $data->name,
            'address' => $data->address,
            'city' => $data->city,
            'state' => $data->state,
        ]);

        return $zone->fresh();
    }
}
