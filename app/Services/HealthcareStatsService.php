<?php

namespace App\Services;

use App\Models\Prescription;
use Illuminate\Database\Eloquent\Model;

class HealthcareStatsService
{
    /**
     * Get medical history and financial summary for a specific patient (Orphan/Widow).
     */
    public function getPatientStats(Model $patient): array
    {
        // Fetch all prescriptions for this polymorphic patient
        $prescriptions = Prescription::where('prescribable_id', $patient->id)
            ->where('prescribable_type', get_class($patient))
            ->get();

        $totalLabCost = (float) $prescriptions->sum('lab_test_cost');
        $totalDrugCost = (float) $prescriptions->sum('drug_cost');
        $netTotal = $totalLabCost + $totalDrugCost;

        return [
            'prescriptions' => $prescriptions,
            'total_lab_test_cost' => $totalLabCost,
            'total_drug_cost' => $totalDrugCost,
            'net_total' => $netTotal,
        ];
    }

    /**
     * Get all prescriptions for the current month (Admin Dashboard view).
     */
    public function getCurrentMonthPrescriptions()
    {
        return Prescription::with('prescribable')
            ->whereYear('prescription_date', now()->year)
            ->whereMonth('prescription_date', now()->month)
            ->orderByDesc('prescription_date')
            ->paginate(10);
    }
}
