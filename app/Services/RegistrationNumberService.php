<?php

namespace App\Services;

use App\Models\Deceased;
use App\Models\Orphan;
use App\Models\Widow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RegistrationNumberService
{
    public function generateDeceasedRegNo(): string
    {
        $year = Carbon::now()->year;

        $count = Deceased::withTrashed()
            ->whereYear('created_at', $year)
            ->count();

        return 'GOF/' . $year . '/' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Generate Widow sequence + reg_no safely
     * @throws \Throwable
     */
    public function generateWidowData(Deceased $deceased): array
    {
        return DB::transaction(function () use ($deceased) {

            // ✅ Lock rows (NOT aggregate)
            $last = Widow::where('deceased_id', $deceased->id)
                ->lockForUpdate()
                ->orderByDesc('child_sequence')
                ->first();

            $next = ($last?->child_sequence ?? 0) + 1;

            return [
                'child_sequence' => $next,
                'reg_no' => $deceased->reg_no . '/W/' . str_pad($next, 2, '0', STR_PAD_LEFT),
            ];
        });
    }

    /**
     * Generate Orphan sequence + reg_no safely
     * @throws \Throwable
     */
    public function generateOrphanData(Deceased $deceased): array
    {
        return DB::transaction(function () use ($deceased) {

            $last = Orphan::where('deceased_id', $deceased->id)
                ->lockForUpdate()
                ->orderByDesc('child_sequence')
                ->first();

            $next = ($last?->child_sequence ?? 0) + 1;

            return [
                'child_sequence' => $next,
                'reg_no' => $deceased->reg_no . '/' . str_pad($next, 2, '0', STR_PAD_LEFT),
            ];
        });
    }
}
