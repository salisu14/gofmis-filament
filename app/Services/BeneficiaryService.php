<?php

namespace App\Services;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Services\Welfare\WelfareNominationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class BeneficiaryService
{
    public function __construct(
        protected WelfareNominationService $nominationService
    ) {}

    /**
     * Single-nomination API. Delegates to the canonical nomination authority.
     *
     * Kept for backward compatibility; it is not used by any Filament path.
     */
    public function suggestBeneficiary(WelfarePackage $package, string $deceasedId, ?string $suggestedBy = null): WelfareBeneficiary
    {
        $user = $suggestedBy
            ? User::find($suggestedBy)
            : auth()->user();

        if (! $user) {
            throw new InvalidArgumentException('A nominating user is required.');
        }

        try {
            return $this->nominationService->nominateSingle($package->id, $deceasedId, $user);
        } catch (RuntimeException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'deceased_id' => $e->getMessage(),
            ]);
        }
    }

    public function approveBeneficiary(WelfareBeneficiary $beneficiary, ?string $approvedBy = null): WelfareBeneficiary
    {
        return DB::transaction(function () use ($beneficiary, $approvedBy) {
            $locked = WelfareBeneficiary::where('id', $beneficiary->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->canBeApproved()) {
                throw new RuntimeException('This beneficiary cannot be approved.');
            }

            $locked->markAsApproved($approvedBy);

            \App\Events\BeneficiaryApproved::dispatch($locked);

            return $locked->fresh();
        });
    }

    public function rejectBeneficiary(WelfareBeneficiary $beneficiary, string $reason, ?string $rejectedBy = null): WelfareBeneficiary
    {
        if (empty($reason)) {
            throw new InvalidArgumentException('Rejection reason is required.');
        }

        return DB::transaction(function () use ($beneficiary, $reason, $rejectedBy) {
            $locked = WelfareBeneficiary::where('id', $beneficiary->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->canBeRejected()) {
                throw new RuntimeException('This beneficiary cannot be rejected.');
            }

            $locked->markAsRejected($reason, $rejectedBy);

            return $locked->fresh();
        });
    }

    /**
     * Canonical single collection. Requires ADMIN fulfilment authorization
     * (super_admin allowed by the repository Gate::before convention) and
     * enforces eligibility revalidation + stock availability transactionally.
     */
    public function collectPackage(WelfareBeneficiary $beneficiary, ?string $notes = null, ?string $collectedBy = null): WelfareBeneficiary
    {
        $user = $collectedBy
            ? User::find($collectedBy)
            : auth()->user();

        if (! $user) {
            throw new InvalidArgumentException('A collecting user is required.');
        }

        if (! $user->hasAnyRole(['admin', 'super_admin'])) {
            throw new RuntimeException('Only administrators can fulfil welfare collections.');
        }

        return DB::transaction(function () use ($beneficiary, $notes, $collectedBy) {
            $locked = WelfareBeneficiary::where('id', $beneficiary->id)->lockForUpdate()->first();

            if (! $locked || ! $locked->canBeCollected()) {
                throw new RuntimeException('This package cannot be collected. Ensure beneficiary is approved and not already collected.');
            }

            $locked->collect($notes, $collectedBy);

            return $locked->fresh();
        });
    }

    public function bulkApprove(array $beneficiaryIds, ?string $approvedBy = null): int
    {
        return WelfareBeneficiary::whereIn('id', $beneficiaryIds)
            ->pending()
            ->update([
                'status' => BeneficiaryStatus::APPROVED,
                'approved_by' => $approvedBy ?? auth()->id(),
            ]);
    }

    /**
     * Bulk collection iterates the canonical single-record operation so bulk
     * and single collection share identical business semantics (eligibility
     * revalidation, stock posting, event dispatch, locking).
     *
     * @return int number of successfully collected records
     */
    public function bulkCollect(array $beneficiaryIds, ?string $notes = null, ?string $collectedBy = null): int
    {
        $user = $collectedBy
            ? User::find($collectedBy)
            : auth()->user();

        if (! $user) {
            throw new InvalidArgumentException('A collecting user is required.');
        }

        if (! $user->hasAnyRole(['admin', 'super_admin'])) {
            throw new RuntimeException('Only administrators can fulfil welfare collections.');
        }

        $collected = 0;

        DB::transaction(function () use ($beneficiaryIds, $notes, $collectedBy, &$collected) {
            foreach ($beneficiaryIds as $id) {
                $locked = WelfareBeneficiary::where('id', $id)->lockForUpdate()->first();

                if (! $locked || ! $locked->canBeCollected()) {
                    continue;
                }

                try {
                    $locked->collect($notes, $collectedBy);
                    $collected++;
                } catch (RuntimeException $e) {
                    // Skip ineligible / insufficient-stock records; keep the
                    // remainder consistent. The caller is notified via count.
                    continue;
                }
            }
        });

        return $collected;
    }

    public function getBeneficiaryDetails(WelfareBeneficiary $beneficiary): array
    {
        $package = $beneficiary->welfarePackage;
        $deceased = $beneficiary->deceased;

        return [
            'beneficiary' => $beneficiary->toArray(),
            'package_name' => $package->name,
            'package_period' => "{$package->start_date->format('M d, Y')} - {$package->end_date->format('M d, Y')}",
            'deceased_name' => $deceased->name ?? 'N/A',
            'items' => $package->items->map(fn ($item) => [
                'item_name' => $item->item->name ?? 'N/A',
                'category' => $item->category->name ?? 'N/A',
                'quantity' => $item->quantity_per_family,
            ]),
            'suggested_by' => $beneficiary->suggester->name ?? 'N/A',
            'approved_by' => $beneficiary->approver->name ?? 'N/A',
            'collected_by' => $beneficiary->collector->name ?? 'N/A',
            'can_collect' => $beneficiary->canBeCollected(),
            'can_approve' => $beneficiary->canBeApproved(),
            'can_reject' => $beneficiary->canBeRejected(),
        ];
    }
}
