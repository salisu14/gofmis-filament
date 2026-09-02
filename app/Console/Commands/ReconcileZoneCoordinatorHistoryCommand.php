<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Zone;
use App\Models\ZoneCoordinatorHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileZoneCoordinatorHistoryCommand extends Command
{
    protected $signature = 'gof:reconcile-coordinator-history {--dry-run : Output planned reconciliation actions without mutating the database}';

    protected $description = 'Detect and reconcile duplicate open coordinator history records to ensure single-active assignment invariants.';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');

        $this->info($isDryRun ? '--- DRY-RUN MODE: Scanning for history anomalies ---' : '--- Starting Coordinator Assignment History Reconciliation ---');

        $actions = $this->reconcileData($isDryRun);

        if (empty($actions)) {
            $this->info('No duplicate active history records found. Database assignment history is clean.');

            return 0;
        }

        foreach ($actions as $action) {
            $this->line($isDryRun ? "[WOULD REPAIR] {$action}" : "[REPAIRED] {$action}");
        }

        $this->info(sprintf('%s Completed %d reconciliation actions.', $isDryRun ? '[DRY-RUN]' : '[SUCCESS]', count($actions)));

        return 0;
    }

    /**
     * Bounded repair/reconciliation logic for open history rows (unassigned_at IS NULL).
     */
    public function reconcileData(bool $isDryRun = false): array
    {
        $actions = [];

        DB::transaction(function () use (&$actions, $isDryRun) {
            // A. Inspect zones with multiple open history records (unassigned_at IS NULL)
            $zoneIds = DB::table('zone_coordinator_histories')
                ->select('zone_id')
                ->whereNull('unassigned_at')
                ->groupBy('zone_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('zone_id');

            foreach ($zoneIds as $zoneId) {
                $zone = Zone::find($zoneId);
                $canonicalCoordId = $zone?->coordinator_id;

                $openHistories = ZoneCoordinatorHistory::where('zone_id', $zoneId)
                    ->whereNull('unassigned_at')
                    ->orderBy('assigned_at', 'asc')
                    ->get();

                // Canonical history row: row matching zone's current coordinator_id (take the latest if multiple)
                $canonicalHistory = $canonicalCoordId
                    ? $openHistories->where('user_id', $canonicalCoordId)->last()
                    : $openHistories->last();

                foreach ($openHistories as $history) {
                    if ($canonicalHistory && $history->id === $canonicalHistory->id) {
                        // Keep open
                        continue;
                    }

                    // Sensible timestamp for closing: use next assignment's assigned_at timestamp if available, else now()
                    $nextHistory = $openHistories->where('assigned_at', '>', $history->assigned_at)->first();
                    $unassignedAt = $nextHistory?->assigned_at ?? now();

                    $actions[] = sprintf(
                        'Zone %s (%s): Closed duplicate active history for %s (Assigned: %s) with unassigned_at: %s',
                        $zone?->name ?? $zoneId,
                        $zoneId,
                        $history->coordinator?->name ?? $history->user_id,
                        $history->assigned_at,
                        $unassignedAt
                    );

                    if (! $isDryRun) {
                        $history->update([
                            'unassigned_at' => $unassignedAt,
                            'reason' => $history->reason ?? 'Closed during coordinator assignment history reconciliation',
                        ]);
                    }
                }
            }

            // B. Inspect users/coordinators with open history records across MULTIPLE zones
            $userIds = DB::table('zone_coordinator_histories')
                ->select('user_id')
                ->whereNull('unassigned_at')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                $user = User::find($userId);
                $canonicalZoneId = $user?->coordinatedZone?->id;

                $userOpenHistories = ZoneCoordinatorHistory::where('user_id', $userId)
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

                    $unassignedAt = now();

                    $actions[] = sprintf(
                        'User %s (%s): Closed multi-zone active history for zone %s with unassigned_at: %s',
                        $user?->name ?? $userId,
                        $userId,
                        $history->zone_id,
                        $unassignedAt
                    );

                    if (! $isDryRun) {
                        $history->update([
                            'unassigned_at' => $unassignedAt,
                            'reason' => $history->reason ?? 'Closed during multi-zone coordinator history reconciliation',
                        ]);
                    }
                }
            }
        });

        return $actions;
    }
}
