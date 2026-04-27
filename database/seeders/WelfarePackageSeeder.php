<?php

namespace Database\Seeders;

use App\Enums\WelfarePackageStatus;
use App\Models\Category;
use App\Models\Deceased;
use App\Models\Item;
use App\Models\User;
use App\Models\WelfareBeneficiary;
use App\Models\WelfarePackage;
use App\Models\WelfarePackageItem;
use Illuminate\Database\Seeder;

class WelfarePackageSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::role('admin')->first() ?? User::factory()->create();
        $coordinators = User::role('coordinator')->take(3)->get();

        if ($coordinators->isEmpty()) {
            $coordinators = User::factory(3)->create();
        }

        $items = Item::take(10)->get();
        $categories = Category::take(5)->get();
        $deceased = Deceased::take(20)->get();

        // Create packages in different states
        foreach ([WelfarePackageStatus::DRAFT, WelfarePackageStatus::OPEN, WelfarePackageStatus::CLOSED] as $status) {
            $package = WelfarePackage::factory()->create([
                'status' => $status,
                'created_by' => $admin->id,
                'approved_by' => in_array($status, [WelfarePackageStatus::OPEN, WelfarePackageStatus::CLOSED]) ? $admin->id : null,
                'approved_at' => in_array($status, [WelfarePackageStatus::OPEN, WelfarePackageStatus::CLOSED]) ? now() : null,
            ]);

            // Add items
            foreach ($items->random(3) as $item) {
                WelfarePackageItem::create([
                    'welfare_package_id' => $package->id,
                    'item_id' => $item->id,
                    'category_id' => $categories->random()->id,
                    'quantity_per_family' => rand(1, 5),
                ]);
            }

            // Add beneficiaries for open/closed packages
            if (in_array($status, [WelfarePackageStatus::OPEN, WelfarePackageStatus::CLOSED])) {
                foreach ($deceased->random(10) as $deceasedPerson) {
                    WelfareBeneficiary::factory()->create([
                        'welfare_package_id' => $package->id,
                        'deceased_id' => $deceasedPerson->id,
                        'suggested_by' => $coordinators->random()->id,
                    ]);
                }
            }
        }
    }
}
