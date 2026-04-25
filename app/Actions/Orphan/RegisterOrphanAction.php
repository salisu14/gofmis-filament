<?php

namespace App\Actions\Orphan;

use App\Data\Orphan\OrphanData;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Services\OrphanEligibilityService;
use App\Services\RegistrationNumberService;
use App\Traits\HasImageUpload;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RegisterOrphanAction
{
    use HasImageUpload;

    public function __construct(
        private RegistrationNumberService $regNoService
    ) {}

    public function execute(OrphanData $data): Orphan
    {
        $deceased = Deceased::findOrFail($data->deceasedId);

        return DB::transaction(function () use ($data, $deceased) {
            // 1. Handle Image Upload
            $picturePath = $this->uploadImage($data->picture, 'orphans');

            // 2. Calculate Age from Birth Date
            $age = Carbon::parse($data->birthDate)->age;

            // 3. Create Orphan
            $orphan = Orphan::create([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'gender' => $data->gender,
                'nin' => $data->nin,
                'reg_no' => $this->regNoService->generateOrphanRegNo(),
                'birth_date' => $data->birthDate,
                'age' => $age, // Auto-calculated
                'guardian_name' => $data->guardianName,
                'guardian_phone' => $data->guardianPhone,
                'address' => $data->address,
                'picture_url' => $picturePath, // Stored path
                'deceased_id' => $deceased->id,
                'western_education_id' => $data->westernEducationId,
                'islamiyya_education_id' => $data->islamiyyaEducationId,
                'birth_certificate_path' => $data->birthCertificatePath,
            ]);

            // 4. Update Deceased Orphan Count
            $deceased->increment('orphan_count');

            // 5. Check Eligibility immediately (Optional)
             if ((new OrphanEligibilityService())->isEligible($orphan)) {
                 // TODO::
                 return $orphan;
             }

            return $orphan;
        });
    }
}
