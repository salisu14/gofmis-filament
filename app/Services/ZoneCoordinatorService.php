<?php

namespace App\Services;

use App\Models\Zone;
use App\Models\ZoneCoordinatorHistory;
use Illuminate\Support\Facades\DB;
use Throwable;

class ZoneCoordinatorService
{
    /**
     * @throws Throwable
     */
    public function assignCoordinator(Zone $zone, string $userId, ?string $changedBy = null): void
    {
        DB::transaction(function () use ($zone, $userId, $changedBy) {

            // Close previous coordinator history
            ZoneCoordinatorHistory::where('zone_id', $zone->id)
                ->whereNull('unassigned_at')
                ->update([
                    'unassigned_at' => now(),
                ]);

            // Save new coordinator
            $zone->update([
                'coordinator_id' => $userId,
            ]);

            // Create history record
            ZoneCoordinatorHistory::create([
                'zone_id' => $zone->id,
                'user_id' => $userId,
                'assigned_at' => now(),
                'changed_by' => $changedBy,
            ]);
        });
    }
}
