<?php

namespace Database\Seeders;

use App\Models\ApprovalFlow;
use App\Models\ApprovalStep;
use App\Models\Widow;
use App\Models\WidowLoan;
use App\Enums\WidowLoanStatus;
use Illuminate\Database\Seeder;

class WidowLoanWithApprovalsSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create test widows
        $widows = Widow::when(Widow::count() == 0, function ($query) {
            return $query->factory(3)->create();
        })->take(3);

        foreach ($widows as $widow) {
            // Create a draft loan
            $draftLoan = WidowLoan::create([
                'widow_id' => $widow->id,
                'principal_amount' => 50000,
                'duration_months' => 12,
                'purpose' => 'Business startup capital',
                'status' => WidowLoanStatus::DRAFT,
            ]);

            // Create a pending loan with approval workflow
            $pendingLoan = WidowLoan::create([
                'widow_id' => $widow->id,
                'principal_amount' => 100000,
                'duration_months' => 24,
                'purpose' => 'Agricultural equipment',
                'status' => WidowLoanStatus::PENDING,
            ]);

            // Create approval flow for pending loan
            $flow = ApprovalFlow::create([
                'model_type' => WidowLoan::class,
                'model_id' => $pendingLoan->id,
                'status' => 'pending',
                'current_step' => 1,
                'total_steps' => 3,
            ]);

            // Create approval steps
            ApprovalStep::create([
                'approval_flow_id' => $flow->id,
                'step_number' => 1,
                'role_required' => 'loan_officer',
                'status' => 'pending',
            ]);

            ApprovalStep::create([
                'approval_flow_id' => $flow->id,
                'step_number' => 2,
                'role_required' => 'finance_manager',
                'status' => 'waiting',
            ]);

            ApprovalStep::create([
                'approval_flow_id' => $flow->id,
                'step_number' => 3,
                'role_required' => 'director',
                'status' => 'waiting',
            ]);

            // Create an approved loan
            $approvedLoan = WidowLoan::create([
                'widow_id' => $widow->id,
                'principal_amount' => 75000,
                'duration_months' => 18,
                'purpose' => 'Trading business',
                'status' => WidowLoanStatus::APPROVED,
                'total_payable' => 75000,
            ]);

            // Create completed approval flow
            $completedFlow = ApprovalFlow::create([
                'model_type' => WidowLoan::class,
                'model_id' => $approvedLoan->id,
                'status' => 'approved',
                'current_step' => 3,
                'total_steps' => 3,
                'approved_at' => now()->subDays(5),
            ]);

            // Create completed approval steps
            for ($i = 1; $i <= 3; $i++) {
                ApprovalStep::create([
                    'approval_flow_id' => $completedFlow->id,
                    'step_number' => $i,
                    'role_required' => ['loan_officer', 'finance_manager', 'director'][$i - 1],
                    'status' => 'approved',
                    'approver_id' => null,
                    'approved_at' => now()->subDays(6 - $i),
                ]);
            }
        }

        $this->command->info('Widow loans with approval workflows created successfully!');
    }
}

