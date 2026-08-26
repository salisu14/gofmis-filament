<?php

namespace App\Services\Welfare;

use App\Enums\BeneficiaryStatus;
use App\Enums\CollectionStatus;
use App\Enums\WelfarePackageStatus;
use App\Models\Deceased;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Canonical server-side authority for Welfare nominations.
 *
 * Every production nomination entry point (Filament actions, relation-manager
 * CreateAction, coordinator create form, services) MUST delegate here. No
 * other code path may create WelfareBeneficiary nomination records for
 * production data.
 *
 * All validation below is server-side. UI filtering is convenience only and
 * is never treated as authorization.
 */
class WelfareNominationService
{
    /**
     * Nominate multiple Deceased households for an open Welfare package.
     *
     * @param  array<string>  $deceasedIds
     * @return array{nominated_count: int, duplicates_count: int, ineligible_count: int, messages: array<string>}
     */
    public function nominate(string $welfarePackageId, array $deceasedIds, User $user): array
    {
        $package = WelfarePackage::find($welfarePackageId);

        $this->assertPackageAcceptingNominations($package);

        $nominatedCount = 0;
        $duplicatesCount = 0;
        $ineligibleCount = 0;
        $messages = [];

        DB::transaction(function () use ($package, $deceasedIds, $user, &$nominatedCount, &$duplicatesCount, &$ineligibleCount, &$messages) {
            $isCoordinator = ! $user->hasAnyRole(['admin', 'super_admin']);
            $userZoneId = $user->coordinatedZone?->id;

            foreach ($deceasedIds as $deceasedId) {
                $deceased = Deceased::with(['widows', 'orphans'])->find($deceasedId);

                if (! $deceased) {
                    $ineligibleCount++;
                    $messages[] = "Deceased record {$deceasedId} not found.";

                    continue;
                }

                // Check zone scoping for Coordinator
                if ($isCoordinator && ($deceased->zone_id !== $userZoneId)) {
                    $ineligibleCount++;
                    $messages[] = "Deceased {$deceased->display_name} is outside your assigned zone.";

                    continue;
                }

                // Check active nomination duplicates: (welfare_package_id, deceased_id) where status != REJECTED
                $existingActiveNomination = WelfareBeneficiary::where('welfare_package_id', $package->id)
                    ->where('deceased_id', $deceased->id)
                    ->where('status', '!=', BeneficiaryStatus::REJECTED->value)
                    ->exists();

                if ($existingActiveNomination) {
                    $duplicatesCount++;
                    $messages[] = "Deceased {$deceased->display_name} is already nominated for {$package->name}.";

                    continue;
                }

                // Verify household has at least one operational, eligible beneficiary
                if (! $this->householdHasEligibleBeneficiary($deceased)) {
                    $ineligibleCount++;
                    $messages[] = "Deceased {$deceased->display_name} has no eligible operational beneficiaries.";

                    continue;
                }

                // Create WelfareBeneficiary nomination record
                try {
                    WelfareBeneficiary::create([
                        'welfare_package_id' => $package->id,
                        'deceased_id' => $deceased->id,
                        'suggested_by' => $user->id,
                        'status' => BeneficiaryStatus::PENDING,
                        'collection_status' => CollectionStatus::NOT_COLLECTED,
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException|\Illuminate\Database\QueryException $e) {
                    // Concurrent duplicate attempt: the unique_package_deceased
                    // constraint is the authoritative PostgreSQL-compatible guard.
                    if (str_contains($e->getMessage(), 'unique_package_deceased') || str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
                        $duplicatesCount++;
                        $messages[] = "Deceased {$deceased->display_name} is already nominated for {$package->name}.";

                        continue;
                    }

                    throw $e;
                }

                $nominatedCount++;
            }
        });

        return [
            'nominated_count' => $nominatedCount,
            'duplicates_count' => $duplicatesCount,
            'ineligible_count' => $ineligibleCount,
            'messages' => $messages,
        ];
    }

    /**
     * Nominate a single household. Delegates to the canonical bulk path so the
     * two entry points share exactly one validation stack.
     */
    public function nominateSingle(string $welfarePackageId, string $deceasedId, User $user): WelfareBeneficiary
    {
        $result = $this->nominate($welfarePackageId, [$deceasedId], $user);

        if ($result['nominated_count'] === 0) {
            $message = $result['messages'][0] ?? 'The household could not be nominated.';
            throw new RuntimeException($message);
        }

        $beneficiary = WelfareBeneficiary::where('welfare_package_id', $welfarePackageId)
            ->where('deceased_id', $deceasedId)
            ->latest('created_at')
            ->first();

        if (! $beneficiary) {
            throw new RuntimeException('Nomination was recorded but the beneficiary record could not be retrieved.');
        }

        return $beneficiary;
    }

    /**
     * Whether a household contains at least one operational AND eligible
     * widow/orphan, per the domain eligibility helpers.
     */
    public function householdHasEligibleBeneficiary(Deceased $deceased): bool
    {
        return $deceased->widows->contains(fn ($w) => $w->isOperationalBeneficiary() && $w->is_eligible)
            || $deceased->orphans->contains(fn ($o) => $o->isOperationalBeneficiary() && $o->is_eligible);
    }

    /**
     * Server-side gate shared by every nomination entry point.
     *
     * @throws InvalidArgumentException when the package is missing or not
     *                                  accepting nominations (wrong status or
     *                                  outside its valid date window).
     */
    protected function assertPackageAcceptingNominations(?WelfarePackage $package): void
    {
        if (! $package) {
            throw new InvalidArgumentException('Welfare package not found.');
        }

        if ($package->status !== WelfarePackageStatus::OPEN) {
            throw new InvalidArgumentException('Selected Welfare intervention is not open for nominations.');
        }

        if ($package->start_date && $package->start_date->isFuture()) {
            throw new InvalidArgumentException('This welfare package has not yet opened for nominations.');
        }

        if ($package->end_date && $package->end_date->isPast()) {
            throw new InvalidArgumentException('This welfare package has ended.');
        }
    }
}
