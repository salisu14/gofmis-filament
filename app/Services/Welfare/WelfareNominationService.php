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

        if (! $package || $package->status !== WelfarePackageStatus::OPEN) {
            throw new \InvalidArgumentException('Selected Welfare intervention is not open for nominations.');
        }

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
                $hasOperationalWidow = $deceased->widows->contains(fn ($w) => $w->isOperationalBeneficiary() && $w->is_eligible);
                $hasOperationalOrphan = $deceased->orphans->contains(fn ($o) => $o->isOperationalBeneficiary() && $o->is_eligible);

                if (! $hasOperationalWidow && ! $hasOperationalOrphan) {
                    $ineligibleCount++;
                    $messages[] = "Deceased {$deceased->display_name} has no eligible operational beneficiaries.";

                    continue;
                }

                // Create WelfareBeneficiary nomination record
                WelfareBeneficiary::create([
                    'welfare_package_id' => $package->id,
                    'deceased_id' => $deceased->id,
                    'suggested_by' => $user->id,
                    'status' => BeneficiaryStatus::PENDING,
                    'collection_status' => CollectionStatus::NOT_COLLECTED,
                ]);

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
}
