<?php

namespace App\Services;

use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneCoordinatorHistory;
use Illuminate\Support\Facades\DB;

class ZoneCoordinatorService
{
    /**
     * Assign or reassign a coordinator to a zone, or unassign if newCoordinatorId is null.
     * Executes atomically inside a database transaction with row locking.
     */
    public function assignCoordinator(Zone $zone, ?string $newCoordinatorId, ?string $changedBy = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($zone, $newCoordinatorId, $changedBy, $reason) {
            // 1. Lock the target Zone row to prevent concurrent assignment races
            $zoneRecord = Zone::where('id', $zone->id)->lockForUpdate()->first();
            if (! $zoneRecord) {
                return;
            }

            // 2. Fetch all currently open history rows for this zone
            $openZoneHistories = ZoneCoordinatorHistory::where('zone_id', $zoneRecord->id)
                ->whereNull('unassigned_at')
                ->get();

            // 3. Idempotency Check:
            // If newCoordinatorId is the same as current zone coordinator pointer AND
            // there is exactly ONE open history record matching newCoordinatorId, it is a no-op.
            if (
                $newCoordinatorId !== null &&
                $zoneRecord->coordinator_id === $newCoordinatorId &&
                $openZoneHistories->count() === 1 &&
                $openZoneHistories->first()->user_id === $newCoordinatorId
            ) {
                return;
            }

            // Inspect whether incoming coordinator has open history rows in ANY OTHER zone
            $otherOpenHistories = $newCoordinatorId !== null
                ? ZoneCoordinatorHistory::where('user_id', $newCoordinatorId)
                    ->where('zone_id', '!=', $zoneRecord->id)
                    ->whereNull('unassigned_at')
                    ->get()
                : collect();

            // Determine reassignment status before mutations
            $isReassignment = $openZoneHistories->isNotEmpty() || $otherOpenHistories->isNotEmpty() || ($zoneRecord->coordinator_id !== null && $zoneRecord->coordinator_id !== $newCoordinatorId);
            $unassignedTimestamp = now();
            $actorId = $changedBy ?? auth()->id();

            // 4. Close ALL open history rows for this zone that do NOT belong to newCoordinatorId
            foreach ($openZoneHistories as $history) {
                if ($history->user_id !== $newCoordinatorId) {
                    $history->update([
                        'unassigned_at' => $unassignedTimestamp,
                        'changed_by' => $actorId,
                        'reason' => $reason ?? 'Reassigned to another coordinator',
                    ]);
                }
            }

            // 5. If assigning a new coordinator (newCoordinatorId is not null):
            if ($newCoordinatorId !== null) {
                // Inspect whether the incoming coordinator has open history rows in ANY OTHER zone
                $otherOpenHistories = ZoneCoordinatorHistory::where('user_id', $newCoordinatorId)
                    ->where('zone_id', '!=', $zoneRecord->id)
                    ->whereNull('unassigned_at')
                    ->get();

                foreach ($otherOpenHistories as $otherHistory) {
                    // Lock that previous zone and clear its coordinator_id pointer
                    $prevZone = Zone::where('id', $otherHistory->zone_id)->lockForUpdate()->first();
                    if ($prevZone && $prevZone->coordinator_id === $newCoordinatorId) {
                        $prevZone->updateQuietly(['coordinator_id' => null]);
                    }

                    $otherHistory->update([
                        'unassigned_at' => $unassignedTimestamp,
                        'changed_by' => $actorId,
                        'reason' => $reason ?? 'Transferred to zone '.$zoneRecord->name,
                    ]);
                }

                // Also clear coordinator_id pointer on any other zone pointing to newCoordinatorId
                $otherZonesWithCoord = Zone::where('coordinator_id', $newCoordinatorId)
                    ->where('id', '!=', $zoneRecord->id)
                    ->get();

                foreach ($otherZonesWithCoord as $otherZone) {
                    $otherZone->updateQuietly(['coordinator_id' => null]);
                }

                // Update target zone's coordinator pointer
                $zoneRecord->updateQuietly(['coordinator_id' => $newCoordinatorId]);

                // Ensure exactly ONE active history row exists for ($zoneRecord->id, $newCoordinatorId)
                $existingTargetOpenHistory = ZoneCoordinatorHistory::where('zone_id', $zoneRecord->id)
                    ->where('user_id', $newCoordinatorId)
                    ->whereNull('unassigned_at')
                    ->first();

                if (! $existingTargetOpenHistory) {
                    ZoneCoordinatorHistory::create([
                        'zone_id' => $zoneRecord->id,
                        'user_id' => $newCoordinatorId,
                        'assigned_at' => now(),
                        'unassigned_at' => null,
                        'changed_by' => $actorId,
                        'reason' => $reason,
                    ]);
                }
            } else {
                // Unassigning: clear target zone's coordinator pointer
                $zoneRecord->updateQuietly(['coordinator_id' => null]);
            }

            // 6. Security Audit Logging
            $eventType = $isReassignment ? 'coordinator.zone_reassigned' : 'coordinator.zone_assigned';
            if ($newCoordinatorId === null) {
                $eventType = 'coordinator.zone_assignment_ended';
            }

            SecurityAuditService::log(
                $eventType,
                "Coordinator zone assignment updated for zone {$zoneRecord->name}",
                $actorId ? User::find($actorId) : auth()->user(),
                $zoneRecord,
                [
                    'zone_id' => $zoneRecord->id,
                    'new_coordinator_id' => $newCoordinatorId,
                    'reason' => $reason,
                ]
            );
        });
    }

    /**
     * Explicit reassign helper.
     */
    public function reassignCoordinator(string $userId, ?Zone $newZone, ?string $changedBy = null, ?string $reason = null): void
    {
        if (! $newZone) {
            $currentZone = Zone::where('coordinator_id', $userId)->first();
            if ($currentZone) {
                $this->assignCoordinator($currentZone, null, $changedBy, $reason);
            }

            return;
        }

        $this->assignCoordinator($newZone, $userId, $changedBy, $reason);
    }

    /**
     * Explicit end assignment helper.
     */
    public function endAssignment(Zone $zone, ?string $changedBy = null, ?string $reason = null): void
    {
        $this->assignCoordinator($zone, null, $changedBy, $reason);
    }
}
