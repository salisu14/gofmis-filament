<?php

namespace App\Actions\Orphan;

use App\Data\Orphan\OrphanData;
use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\OrphanVocationalSkill;
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
            $registrationData = $this->regNoService->generateOrphanData($deceased);

            $picturePath = $data->picture instanceof UploadedFile
                ? $this->uploadImage($data->picture, 'orphans')
                : $data->picture;

            if (is_array($picturePath)) {
                $picturePath = reset($picturePath) ?: null;
            }

            $birthCertificatePath = $data->birthCertificatePath;

            if (is_array($birthCertificatePath)) {
                $birthCertificatePath = reset($birthCertificatePath) ?: null;
            }

            $age = Carbon::parse($data->birthDate)->age;

            $orphan = Orphan::create([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'middle_name' => $data->middleName,
                'gender' => $data->gender,
                'nin' => $data->nin,
                'reg_no' => $registrationData['reg_no'],
                'birth_date' => $data->birthDate,
                'age' => $age,
                'address' => $data->address,
                'picture_url' => $picturePath,
                'deceased_id' => $deceased->id,
                'child_sequence' => $registrationData['child_sequence'],
                'has_birth_cert' => $data->hasBirthCert,
                'birth_certificate_path' => $birthCertificatePath,
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
                $orphan->vocationalSkills()->detach();

                $skillRows = collect($data->vocationalSkills)
                    ->map(function ($skill) use ($orphan) {
                        if (is_array($skill)) {
                            $skillId = $skill['id'] ?? null;
                            $specify = $skill['specify'] ?? null;
                        } else {
                            $skillId = $skill;
                            $specify = null;
                        }

                        if (!$skillId) {
                            return null;
                        }

                        return [
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'orphan_id' => $orphan->id,
                            'vocational_skill_id' => $skillId,
                            'specify' => $specify,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    })
                    ->filter()
                    ->values()
                    ->all();

                if (!empty($skillRows)) {
                    OrphanVocationalSkill::query()->insert($skillRows);
                }
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
