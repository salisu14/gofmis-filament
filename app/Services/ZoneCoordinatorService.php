<?php

namespace App\Services;

use App\Models\Zone;
use App\Models\ZoneCoordinatorHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ZoneCoordinatorService
{
    /**
     * @throws Throwable
     */
    public function assignCoordinator(Zone $zone, string $userId, ?string $changedBy = null): void
    {
        DB::transaction(function () use ($zone, $userId, $changedBy) {

            $exists = Zone::where('coordinator_id', $userId)
                ->where('id', '!=', $zone->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'coordinator_id' => 'This user is already assigned to another zone.',
                ]);
            }

            // Close previous coordinator history
            ZoneCoordinatorHistory::where('zone_id', $zone->id)
                ->whereNull('unassigned_at')
                ->update([
                    'unassigned_at' => now(),
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
