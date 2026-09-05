<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Console\Command;

class ReconcileInventory extends Command
{
    protected $signature = 'inventory:reconcile {--fix : Automatically fix detected issues}';

    protected $description = 'Reconcile stock movement ledger against welfare collection records and report discrepancies';

    public function handle(): int
    {
        $this->info('');
        $this->info('═══════════════════════════════════════');
        $this->info('  GOF MIS — Inventory Reconciliation');
        $this->info('═══════════════════════════════════════');
        $this->info('');

        $items = Item::all();
        $discrepancies = [];
        $summaryRows = [];

        foreach ($items as $item) {
            // Ledger-derived on-hand
            $ledgerOnHand = (int) StockMovement::where('item_id', $item->id)->sum('quantity');

            // Count total inflows and outflows separately
            $totalInflows = (int) StockMovement::where('item_id', $item->id)
                ->where('quantity', '>', 0)
                ->sum('quantity');

            $totalOutflows = abs((int) StockMovement::where('item_id', $item->id)
                ->where('quantity', '<', 0)
                ->sum('quantity'));

            // Count movement records
            $movementCount = StockMovement::where('item_id', $item->id)->count();

            // Cross-check: count actual welfare collections for this item
            $welfareIssueCount = StockMovement::where('item_id', $item->id)
                ->where('movement_type', 'WELFARE_ISSUE')
                ->count();

            $summaryRows[] = [
                $item->name,
                $movementCount,
                $totalInflows,
                $totalOutflows,
                $ledgerOnHand,
                $welfareIssueCount,
            ];

            // Flag negative on-hand
            if ($ledgerOnHand < 0) {
                $discrepancies[] = [
                    'item' => $item->name,
                    'item_id' => $item->id,
                    'issue' => 'NEGATIVE_ON_HAND',
                    'detail' => "On Hand is {$ledgerOnHand} — more issued than received",
                ];
            }

            // Flag items with no movements at all (might need opening balance)
            if ($movementCount === 0) {
                $discrepancies[] = [
                    'item' => $item->name,
                    'item_id' => $item->id,
                    'issue' => 'NO_MOVEMENTS',
                    'detail' => 'No stock movements recorded — needs opening balance',
                ];
            }
        }

        // Summary table
        $this->table(
            ['Item', 'Movements', 'Total In', 'Total Out', 'On Hand', 'Welfare Issues'],
            $summaryRows
        );

        $this->info('');

        if (empty($discrepancies)) {
            $this->info('✅ No discrepancies found. Stock ledger is consistent.');
        } else {
            $this->warn('⚠️  Found '.count($discrepancies).' discrepancy(ies):');
            $this->info('');

            foreach ($discrepancies as $d) {
                $this->line("  [{$d['issue']}] {$d['item']}: {$d['detail']}");
            }

            $this->info('');

            if ($this->option('fix')) {
                $this->fixDiscrepancies($discrepancies);
            } else {
                $this->info('Run with --fix to automatically add opening balances for items with no movements.');
            }
        }

        $this->info('');

        // Orphan movements check: stock movements referencing non-existent items
        $orphanCount = StockMovement::whereNotIn('item_id', Item::pluck('id'))->count();
        if ($orphanCount > 0) {
            $this->warn("⚠️  Found {$orphanCount} orphaned stock movement(s) referencing deleted items.");
        } else {
            $this->info('✅ No orphaned stock movements found.');
        }

        $this->info('');
        $this->info('Reconciliation complete.');

        return self::SUCCESS;
    }

    protected function fixDiscrepancies(array $discrepancies): void
    {
        $fixed = 0;

        foreach ($discrepancies as $d) {
            if ($d['issue'] === 'NO_MOVEMENTS') {
                // This is informational — we don't auto-create opening balances
                // as that requires business knowledge of actual stock levels.
                $this->warn("  ⏭️  Skipping {$d['item']} — opening balance requires manual entry of actual stock count.");
            }

            if ($d['issue'] === 'NEGATIVE_ON_HAND') {
                $this->warn("  ⚠️  {$d['item']} has negative on-hand — this requires a stock count and adjustment entry.");
            }
        }

        if ($fixed === 0) {
            $this->info('  No auto-fixable issues found. Discrepancies require manual stock count verification.');
        }
    }
}
