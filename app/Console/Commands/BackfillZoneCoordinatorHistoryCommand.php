<?php

namespace App\Console\Commands;

use App\Models\Zone;
use App\Models\ZoneCoordinatorHistory;
use Illuminate\Console\Command;

class BackfillZoneCoordinatorHistoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gof:backfill-coordinator-history';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely backfill baseline active history records for existing zone coordinators missing history records.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Coordinator Assignment History backfill...');

        $zones = Zone::whereNotNull('coordinator_id')->get();
        $createdCount = 0;

        foreach ($zones as $zone) {
            $hasActive = ZoneCoordinatorHistory::where('zone_id', $zone->id)
                ->where('user_id', $zone->coordinator_id)
                ->whereNull('unassigned_at')
                ->exists();

            if (! $hasActive) {
                // Use zone created_at if available, otherwise baseline now()
                $assignedAt = $zone->created_at ?? now();

                ZoneCoordinatorHistory::create([
                    'zone_id' => $zone->id,
                    'user_id' => $zone->coordinator_id,
                    'assigned_at' => $assignedAt,
                    'unassigned_at' => null,
                    'changed_by' => null,
                    'reason' => 'Baseline backfill for existing zone assignment',
                ]);

                $createdCount++;
                $this->line("Backfilled active assignment for Zone '{$zone->name}' -> User '{$zone->coordinator_id}'");
            }
        }

        $this->info("Backfill complete. Created {$createdCount} history record(s).");

        return Command::SUCCESS;
    }
}
