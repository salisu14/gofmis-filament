<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Reconcile existing data using direct DB statements (self-contained and deterministic)
        // A. Repair zones with multiple open history records (unassigned_at IS NULL)
        $zoneIds = DB::table('zone_coordinator_histories')
            ->select('zone_id')
            ->whereNull('unassigned_at')
            ->groupBy('zone_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('zone_id');

        foreach ($zoneIds as $zoneId) {
            $zone = DB::table('zones')->where('id', $zoneId)->first();
            $canonicalCoordId = $zone?->coordinator_id;

            $openHistories = DB::table('zone_coordinator_histories')
                ->where('zone_id', $zoneId)
                ->whereNull('unassigned_at')
                ->orderBy('assigned_at', 'asc')
                ->get();

            // Canonical history row: row matching zone's current coordinator_id (take latest if multiple)
            $canonicalHistory = $canonicalCoordId
                ? $openHistories->where('user_id', $canonicalCoordId)->last()
                : $openHistories->last();

            foreach ($openHistories as $history) {
                if ($canonicalHistory && $history->id === $canonicalHistory->id) {
                    continue; // Keep open
                }

                // Determine unassigned_at timestamp: use next assignment's assigned_at if available, else now()
                $nextHistory = $openHistories->where('assigned_at', '>', $history->assigned_at)->first();
                $unassignedAt = $nextHistory?->assigned_at ?? now();

                DB::table('zone_coordinator_histories')
                    ->where('id', $history->id)
                    ->update([
                        'unassigned_at' => $unassignedAt,
                        'reason' => $history->reason ?? 'Closed during coordinator assignment history reconciliation',
                    ]);
            }
        }

        // B. Repair users with open history records across MULTIPLE zones
        $userIds = DB::table('zone_coordinator_histories')
            ->select('user_id')
            ->whereNull('unassigned_at')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $currentZone = DB::table('zones')->where('coordinator_id', $userId)->first();
            $canonicalZoneId = $currentZone?->id;

            $userOpenHistories = DB::table('zone_coordinator_histories')
                ->where('user_id', $userId)
                ->whereNull('unassigned_at')
                ->orderBy('assigned_at', 'asc')
                ->get();

            $canonicalHistory = $canonicalZoneId
                ? $userOpenHistories->where('zone_id', $canonicalZoneId)->last()
                : $userOpenHistories->last();

            foreach ($userOpenHistories as $history) {
                if ($canonicalHistory && $history->id === $canonicalHistory->id) {
                    continue;
                }

                DB::table('zone_coordinator_histories')
                    ->where('id', $history->id)
                    ->update([
                        'unassigned_at' => now(),
                        'reason' => $history->reason ?? 'Closed during multi-zone coordinator history reconciliation',
                    ]);
            }
        }

        // 2. Add partial unique indexes (supported by both SQLite >= 3.8 and PostgreSQL)
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_zch_unique_active_zone ON zone_coordinator_histories (zone_id) WHERE unassigned_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS idx_zch_unique_active_user ON zone_coordinator_histories (user_id) WHERE unassigned_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_zch_unique_active_zone');
        DB::statement('DROP INDEX IF EXISTS idx_zch_unique_active_user');
    }
};
