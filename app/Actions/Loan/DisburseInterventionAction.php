<?php

namespace App\Actions\Loan;

use App\Data\Loan\DisburseInterventionData;
use App\Events\InterventionDisbursed;
use App\Models\Intervention;
use App\Models\InterventionItem;
use Illuminate\Support\Facades\DB;

class DisburseInterventionAction
{
    public function execute(DisburseInterventionData $data): Intervention
    {
        return DB::transaction(function () use ($data) {
            // Create the main intervention record
            $intervention = Intervention::create([
                'intervention_request_id' => $data->interventionRequestId,
                'orphan_id' => $data->orphanId,
                'intervention_type_id' => $data->interventionTypeId,
                'status' => 'completed',
                'disbursed_at' => now(),
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

            event(new InterventionDisbursed($intervention));

            return $intervention;
        });
    }
}
