<?php

namespace App\Actions\Widow;

use App\Data\Widow\WidowData;
use App\Models\Deceased;
use App\Models\Widow;
use App\Services\RegistrationNumberService;
use App\Traits\HasImageUpload;
use Illuminate\Support\Facades\DB;

class RegisterWidowAction
{
    use HasImageUpload;

    public function __construct(
        private readonly RegistrationNumberService $regNoService
    ) {}

    /**
     * @throws \Throwable
     */
    public function execute(WidowData $data): Widow
    {
        $deceased = Deceased::findOrFail($data->deceasedId);

        return DB::transaction(function () use ($data, $deceased) {
            $registrationData = $this->regNoService->generateWidowData($deceased);

            $widow = Widow::create([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'middle_name' => $data->middleName,
                'nin' => $data->nin,
                'reg_no' => $registrationData['reg_no'],
                'child_sequence' => $registrationData['child_sequence'],

                'address' => $data->address,
                'skills' => is_array($data->skills)
                    ? $data->skills
                    : (is_string($data->skills) ? explode(',', $data->skills) : []),

                'is_eligible' => $data->isEligible && ! $data->isMarried,

                'is_married' => $data->isMarried,
                'deceased_id' => $deceased->id,
            ]);

            return $widow;
        });
    }
}
