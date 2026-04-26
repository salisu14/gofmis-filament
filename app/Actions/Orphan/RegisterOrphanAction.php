<?php

namespace App\Actions\Orphan;

use App\Data\Orphan\OrphanData;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Services\OrphanEligibilityService;
use App\Services\RegistrationNumberService;
use App\Traits\HasImageUpload;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RegisterOrphanAction
{
    use HasImageUpload;

    public function __construct(
        private readonly RegistrationNumberService $regNoService,
        private readonly OrphanEligibilityService  $eligibilityService,
    ) {}

    /**
     * @throws \Throwable
     */
    public function execute(OrphanData $data): Orphan
    {
        $deceased = Deceased::findOrFail($data->deceasedId);

        return DB::transaction(function () use ($data, $deceased) {

            $picturePath = $data->picture instanceof UploadedFile
                ? $this->uploadImage($data->picture, 'orphans')
                : $data->picture;

            $age = Carbon::parse($data->birthDate)->age;

            $orphan = Orphan::create([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'middle_name' => $data->middleName,
                'gender' => $data->gender,
                'nin' => $data->nin,
                'reg_no' => $this->regNoService->generateOrphanRegNo($deceased),
                'birth_date' => $data->birthDate,
                'age' => $age,
                'address' => $data->address,
                'picture_url' => $picturePath,
                'deceased_id' => $deceased->id,
                'birth_certificate_path' => $data->birthCertificatePath,
                'status' => 'draft',
            ]);

            // EDUCATION
            foreach ($data->educations ?? [] as $education) {
                $orphan->educations()->create([
                    'institution_id' => $education['institution_id'],
                    'level'          => $education['level'] ?? null,
                    'class_level'    => $education['class_level'] ?? null,
                    'school_fee'     => $education['school_fee'] ?? 0,
                    'fee_frequency'  => $education['fee_frequency'] ?? 'termly',
                    'is_current'     => $education['is_current'] ?? true,
                    'started_at'     => $education['started_at'] ?? now(),
                ]);
            }

            // VOCATIONAL SKILLS
            if (!empty($data->vocationalSkills)) {
                $syncData = collect($data->vocationalSkills)
                    ->filter(fn ($skill) => !empty($skill['id']))
                    ->mapWithKeys(fn ($skill) => [
                        $skill['id'] => ['specify' => $skill['specify'] ?? null]
                    ])
                    ->toArray();

                $orphan->vocationalSkills()->sync($syncData);
            }

            // UPDATE COUNT (SAFE)
            $deceased->update([
                'number_of_orphans_left' => $deceased->orphans()->count()
            ]);

            // ELIGIBILITY
            $isEligible = $this->eligibilityService->isEligible($orphan);

            $orphan->update([
                'is_eligible' => $isEligible,
                'status' => $isEligible ? 'approved' : 'pending_review',
            ]);

            return $orphan;
        });
    }
}
