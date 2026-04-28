<?php
// app/Services/Deceased/ZoneTransferService.php

namespace App\Services\Deceased;

use App\Models\Deceased;
use App\Models\ZoneTransfer;
use Illuminate\Support\Facades\DB;

class ZoneTransferService
{
    /**
     * Transfer a deceased person's family to a new zone.
     *
     * @param Deceased $deceased The deceased record (family head)
     * @param string $toZoneId UUID of the destination zone
     * @param string|null $reason Reason for the transfer
     * @param string|null $performedBy UUID of user performing the transfer (defaults to auth user)
     * @return ZoneTransfer The created transfer record
     * @throws \InvalidArgumentException If transferring to same zone
     */
    public function transfer(
        Deceased $deceased,
        string $toZoneId,
        ?string $reason = null,
        ?string $performedBy = null
    ): ZoneTransfer {

        // Prevent unnecessary transfer
        if ($deceased->zone_id === $toZoneId) {
            throw new \InvalidArgumentException(
                'Cannot transfer to the same zone. The family is already in this zone.'
            );
        }

        return DB::transaction(function () use ($deceased, $toZoneId, $reason, $performedBy) {

            $fromZoneId = $deceased->zone_id;

            // Update deceased zone
            $deceased->update(['zone_id' => $toZoneId]);

            // Create transfer history record
            $transfer = $deceased->zoneTransfers()->create([
                'from_zone_id' => $fromZoneId,
                'to_zone_id'   => $toZoneId,
                'moved_by'     => $performedBy ?? auth()->id(),
                'reason'       => $reason,
            ]);

            // Optional: trigger event
            // event(new DeceasedZoneTransferred($deceased, $transfer));

            return $transfer;
        });
    }
}
