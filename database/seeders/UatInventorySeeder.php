<?php

namespace Database\Seeders;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Deterministic UAT inventory: categories, items and authoritative opening
 * stock posted through the canonical StockMovement ledger.
 *
 * No direct on-hand/derived balances are written — the ledger is canonical.
 * Re-running is idempotent: items are keyed by name, and opening stock
 * movements are keyed by a deterministic (item, type, reference) pair so they
 * are never posted twice.
 */
class UatInventorySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@admin.com')->first()
            ?? User::where('email', 'sadmin@admin.com')->first()
            ?? User::first();

        if (! $user) {
            throw new \RuntimeException('UatInventorySeeder requires at least one user. Run UatHouseholdSeeder first.');
        }

        // Converge the generic baseline fixtures (Category N / Item N) created
        // by WelfarePackageSeeder into realistic deterministic identities.
        $this->convergeLegacyGenericInventory($user);

        $categories = [
            'Food Items' => 'Staple food and cooking supplies for welfare packages',
            'School Supplies' => 'Educational materials for orphans',
            'Uniform & Clothing' => 'School uniforms and clothing support',
            'Medical Supplies' => 'Basic medical kits and healthcare consumables',
            'Household Essentials' => 'Basic household items for welfare distribution',
        ];

        foreach ($categories as $name => $description) {
            Category::firstOrCreate(
                ['name' => $name],
                ['description' => $description, 'user_id' => $user->id]
            );
        }

        // [item name, category, unit_of_measure, reorder_level, opening_qty]
        $items = [
            ['Rice (50kg Bag)', 'Food Items', 'Bags', 20, 120],
            ['Maize (50kg Bag)', 'Food Items', 'Bags', 15, 60],
            ['Cooking Oil (5L)', 'Food Items', 'Gallons', 25, 90],
            ['Beans (25kg Bag)', 'Food Items', 'Bags', 15, 40],
            ['School Bag', 'School Supplies', 'Pieces', 30, 150],
            ['Exercise Books (Pack of 5)', 'School Supplies', 'Packs', 50, 300],
            ['School Uniform', 'Uniform & Clothing', 'Sets', 20, 5],
            ['Basic Medical Kit', 'Medical Supplies', 'Kits', 10, 25],
            ['Mosquito Net', 'Medical Supplies', 'Pieces', 20, 45],
            ['Bar Soap (Carton)', 'Household Essentials', 'Cartons', 10, 18],
            ['Detergent (2kg)', 'Household Essentials', 'Pieces', 15, 35],
            ['Kerosene Stove', 'Household Essentials', 'Pieces', 5, 8],
        ];

        foreach ($items as [$name, $categoryName, $unit, $reorder, $openingQty]) {
            $category = Category::where('name', $categoryName)->first();

            $item = Item::firstOrCreate(
                ['name' => $name],
                [
                    'description' => $name.' (UAT deterministic inventory item)',
                    'category_id' => $category->id,
                    'user_id' => $user->id,
                    'unit_of_measure' => $unit,
                    'reorder_level' => $reorder,
                    'is_active' => true,
                ]
            );

            // Opening stock via the canonical ledger. The deterministic
            // reference key guarantees a second run never posts it twice.
            $this->createOpeningBalance($item, $openingQty, $user);
        }
    }

    /**
     * Post an opening balance on the canonical StockMovement ledger exactly
     * once (keyed by item + movement type + a fixed UAT reference).
     */
    protected function createOpeningBalance(Item $item, int $quantity, User $user): void
    {
        if ($quantity <= 0) {
            return;
        }

        $exists = StockMovement::where('item_id', $item->id)
            ->where('movement_type', StockMovementType::OPENING_BALANCE)
            ->where('reference_type', 'uat_opening')
            ->where('reference_id', $item->id)
            ->exists();

        if ($exists) {
            return;
        }

        StockMovement::create([
            'item_id' => $item->id,
            'movement_type' => StockMovementType::OPENING_BALANCE,
            'quantity' => $quantity,
            'occurred_at' => now()->subMonths(2),
            'reference_type' => 'uat_opening',
            'reference_id' => $item->id,
            'created_by' => $user->id,
            'notes' => "UAT opening balance for {$item->name}",
        ]);
    }

    /**
     * Converge the generic baseline inventory fixtures created by
     * WelfarePackageSeeder ("Category 1..5" / "Item 1..10") into realistic
     * deterministic identities so the UAT/demo environment is presentation-ready.
     *
     * Rules:
     *  - Categories and referenced items are RENAMED in place (update on the
     *    stable id), preserving every foreign key / relationship (welfare
     *    package items, stock movements, intervention request items).
     *  - Items that are COMPLETELY UNREFERENCED (no welfare_package_items, no
     *    stock movements, no intervention_request_items) are removed.
     *
     * Deterministic and idempotent: a second run finds nothing to rename
     * (names already realistic) and nothing to remove (already gone).
     */
    protected function convergeLegacyGenericInventory(User $user): void
    {
        // Category N -> realistic deterministic category identity.
        $categoryRenames = [
            'Category 1' => 'Grains & Staples',
            'Category 2' => 'Pulses & Legumes',
            'Category 3' => 'Cooking Essentials',
            'Category 4' => 'School Materials',
            'Category 5' => 'Household & Personal Care',
        ];

        foreach ($categoryRenames as $oldName => $newName) {
            $category = Category::where('name', $oldName)->first();
            if (! $category) {
                continue;
            }
            $category->update([
                'name' => $newName,
                'description' => 'UAT deterministic category (converged from baseline fixture)',
            ]);
        }

        // Item N -> realistic deterministic item identity [new name, category, unit, reorder].
        $itemRenames = [
            'Item 1' => ['Premium Rice (25kg Bag)', 'Grains & Staples', 'Bags', 15],
            'Item 2' => ['Local Rice (50kg Bag)', 'Grains & Staples', 'Bags', 20],
            'Item 3' => ['Millet (50kg Bag)', 'Grains & Staples', 'Bags', 10],
            'Item 4' => ['Groundnut Oil (5L)', 'Cooking Essentials', 'Gallons', 12],
            'Item 5' => ['Cowpeas (25kg Bag)', 'Pulses & Legumes', 'Bags', 10],
            'Item 6' => ['Sugar (50kg Bag)', 'Cooking Essentials', 'Bags', 10],
            'Item 7' => ['Copy Books (Pack of 3)', 'School Materials', 'Packs', 25],
            'Item 8' => ['Pens (Pack of 10)', 'School Materials', 'Packs', 20],
            'Item 9' => ['Toothpaste', 'Household & Personal Care', 'Pieces', 20],
            'Item 10' => ['Bathing Soap', 'Household & Personal Care', 'Pieces', 30],
        ];

        $removed = 0;
        $renamed = 0;

        foreach ($itemRenames as $oldName => [$newName, $categoryName, $unit, $reorder]) {
            $item = Item::where('name', $oldName)->first();
            if (! $item) {
                continue;
            }

            $category = Category::where('name', $categoryName)->first();

            $referenced = \App\Models\WelfarePackageItem::where('item_id', $item->id)->exists()
                || StockMovement::where('item_id', $item->id)->exists()
                || \Illuminate\Support\Facades\DB::table('intervention_request_items')->where('item_id', $item->id)->exists();

            if (! $referenced) {
                // Provably unreferenced — safe to remove.
                $item->forceDelete();
                $removed++;
                $this->command?->info("  Removed unreferenced generic item {$oldName}");

                continue;
            }

            // Rename in place — preserve id, category FK and all relationships.
            $item->update([
                'name' => $newName,
                'category_id' => $category?->id ?? $item->category_id,
                'unit_of_measure' => $unit,
                'reorder_level' => $reorder,
                'description' => 'UAT deterministic item (converged from baseline fixture)',
                'is_active' => true,
                'user_id' => $user->id,
            ]);

            $renamed++;
            $this->command?->info("  Renamed generic item {$oldName} -> {$newName}");
        }

        if ($renamed > 0 || $removed > 0) {
            $this->command?->info("  Legacy inventory convergence: {$renamed} renamed, {$removed} removed");
        }
    }
}
