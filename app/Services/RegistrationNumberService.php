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
        $prefix = 'GOF/'.$year.'/';

        // ✅ Concurrency protection for PostgreSQL:
        // Acquire an exclusive transaction-level advisory lock for Deceased registration
        // number allocation scoped by year. Automatically released upon COMMIT or ROLLBACK.
        // Lock namespace: 42637 (GOFMIS Deceased reg allocation), sub-key: $year.
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('SELECT pg_advisory_xact_lock(?, ?)', [42637, (int) $year]);
        }

        $rows = Deceased::withoutGlobalScopes()
            ->withTrashed()
            ->where('reg_no', 'like', $prefix.'%')
            ->get(['reg_no']);

        $maxNum = $rows->map(function ($row) use ($prefix) {
            $suffix = substr((string) $row->reg_no, strlen($prefix));

            return is_numeric($suffix) ? (int) $suffix : 0;
        })->max() ?? 0;

        $next = $maxNum + 1;

        // Collision loop handles non-sequential numbers or imported gaps
        do {
            $candidate = $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $next++;
        } while (Deceased::withoutGlobalScopes()->withTrashed()->where('reg_no', $candidate)->exists());

        return $candidate;
    }

    /**
     * Generate Widow sequence + reg_no safely
     *
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
                'reg_no' => $deceased->reg_no.'/W/'.str_pad($next, 2, '0', STR_PAD_LEFT),
            ];
        });
    }

    /**
     * Generate Orphan sequence + reg_no safely
     *
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
                'reg_no' => $deceased->reg_no.'/'.str_pad($next, 2, '0', STR_PAD_LEFT),
            ];
        });
    }
}
