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
    use HasImageUpload; // If you add image upload later

    public function __construct(
        private RegistrationNumberService $regNoService
    ) {}

    public function execute(WidowData $data): Widow
    {
        // Ensure deceased exists
        $deceased = Deceased::findOrFail($data->deceasedId);

        return DB::transaction(function () use ($data, $deceased) {
            $widow = Widow::create([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'nin' => $data->nin,
                'reg_no' => $this->regNoService->generateWidowRegNo(),
                'address' => $data->address,
                'skills' => $data->skills,
                'deceased_id' => $deceased->id,
            ]);

            // Optional: Update Deceased widow count
            $deceased->increment('widow_count');

            return $widow;
        });
    }
}
