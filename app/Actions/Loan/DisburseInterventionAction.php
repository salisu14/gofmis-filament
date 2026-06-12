<?php

namespace App\Actions\Loan;

use App\Data\Loan\DisburseInterventionData;
use App\Events\InterventionDisbursed;
use App\Models\BankAccount;
use App\Models\Intervention;
use App\Models\InterventionRequest;
use App\Models\InterventionItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class DisburseInterventionAction
{
    public function execute(DisburseInterventionData $data): Intervention
    {
        return DB::transaction(function () use ($data) {
            $request = InterventionRequest::findOrFail($data->interventionRequestId);

            if ($request->status !== 'approved') {
                throw new \RuntimeException('Interventions can only be disbursed for approved requests.');
            }

            $bankAccount = null;
            if ($data->bankAccountId && $data->amount) {
                $bankAccount = BankAccount::lockForUpdate()->findOrFail($data->bankAccountId);
                $bankAccount->ensureDedicatedTo(BankAccount::USAGE_INTERVENTION, 'interventions');
                $bankAccount->debit((float) $data->amount);
            }

            // Create the main intervention record
            $intervention = Intervention::create([
                'intervention_request_id' => $data->interventionRequestId,
                'orphan_id' => $data->orphanId,
                'intervention_type_id' => $data->interventionTypeId,
                'bank_account_id' => $data->bankAccountId,
                'amount' => $data->amount,
                'status' => 'completed',
                'disbursed_at' => now(),
                'disbursed_by' => auth()->id(),
                'collected_by' => $data->collectedBy,
                'support_document_url' => $data->supportDocUrl,
            ]);

            // Create the line items
            foreach ($data->items as $item) {
                InterventionItem::create([
                    'intervention_id' => $intervention->id,
                    'item_name' => $item['item_name'],
                    'specification' => $item['specification'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_value' => $item['unit_value'] ?? null,
                ]);
            }

            if ($bankAccount && $data->amount) {
                Transaction::create([
                    'bank_account_id' => $bankAccount->id,
                    'transactionable_type' => Intervention::class,
                    'transactionable_id' => $intervention->id,
                    'reference' => 'INTV-'.strtoupper(substr($intervention->id, 0, 8)),
                    'date' => now(),
                    'type' => 'intervention',
                    'amount' => $data->amount,
                    'description' => "Intervention fulfillment for {$intervention->orphan?->full_name}",
                    'is_system' => true,
                ]);
            }

            event(new InterventionDisbursed($intervention));

            return $intervention;
        });
    }
}
