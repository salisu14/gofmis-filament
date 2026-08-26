<?php

namespace App\Services\Welfare;

use App\Enums\WelfarePackageStatus;
use App\Models\WelfarePackage;
use RuntimeException;

/**
 * Enforces all server-side lifecycle transitions for WelfarePackage.
 *
 * Allowed transitions and their domain guards:
 *
 *   openPackage    DRAFT  → OPEN    requires: at least one WelfarePackageItem
 *   closePackage   OPEN   → CLOSED  no additional domain guards
 *   reopenPackage  CLOSED → OPEN    package items must still exist
 *
 * Structural legality is checked via WelfarePackageStatus::canTransitionTo().
 * Domain legality is checked here before any DB write.
 *
 * This service is the canonical authority for package lifecycle changes.
 * Filament actions MUST delegate here; they must never write status directly.
 */
class WelfarePackageLifecycleService
{
    /**
     * Transition a package from DRAFT to OPEN.
     *
     * @throws RuntimeException when the transition is structurally illegal
     *                          or domain guards are not satisfied
     */
    public function openPackage(WelfarePackage $package): void
    {
        if (! $package->status->canTransitionTo(WelfarePackageStatus::OPEN)) {
            throw new RuntimeException(
                "Cannot open a package with status [{$package->status->value}]. Only DRAFT packages may be opened."
            );
        }

        if ($package->items()->count() === 0) {
            throw new RuntimeException('Cannot open a package that has no items. Add at least one item first.');
        }

        $package->update([
            'status' => WelfarePackageStatus::OPEN,
            'approved_by' => auth()->check() ? auth()->id() : $package->approved_by,
            'approved_at' => now(),
        ]);
    }

    /**
     * Transition a package from OPEN to CLOSED.
     *
     * @throws RuntimeException when the transition is structurally illegal
     */
    public function closePackage(WelfarePackage $package): void
    {
        if (! $package->status->canTransitionTo(WelfarePackageStatus::CLOSED)) {
            throw new RuntimeException(
                "Cannot close a package with status [{$package->status->value}]. Only OPEN packages may be closed."
            );
        }

        $package->update(['status' => WelfarePackageStatus::CLOSED]);
    }

    /**
     * Transition a package from CLOSED back to OPEN.
     *
     * Reopening requires at least one WelfarePackageItem, matching the
     * opening guard. Reopening does NOT restore editability of package
     * composition if nominations already exist (enforced separately by
     * WelfarePackage::isCompositionEditable()).
     *
     * @throws RuntimeException when the transition is structurally illegal
     */
    public function reopenPackage(WelfarePackage $package): void
    {
        // Reopen is strictly CLOSED → OPEN.
        // DRAFT → OPEN is a valid structural transition but belongs to openPackage().
        if (! $package->isClosed()) {
            throw new RuntimeException(
                "Cannot reopen a package with status [{$package->status->value}]. Only CLOSED packages may be reopened."
            );
        }

        if ($package->items()->count() === 0) {
            throw new RuntimeException('Cannot reopen a package that has no items. Add at least one item first.');
        }

        $package->update(['status' => WelfarePackageStatus::OPEN]);
    }
}
