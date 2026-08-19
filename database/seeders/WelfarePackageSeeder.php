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

        // If no categories, create some
        if (Category::count() === 0) {
            for ($i = 1; $i <= 5; $i++) {
                Category::create([
                    'name' => "Category $i",
                    'description' => "Description for Category $i",
                    'user_id' => $admin->id,
                ]);
            }
        }
        $categories = Category::take(5)->get();

        // If no items, create some
        if (Item::count() === 0) {
            for ($i = 1; $i <= 10; $i++) {
                Item::create([
                    'name' => "Item $i",
                    'description' => "Description for Item $i",
                    'category_id' => $categories->random()->id,
                    'user_id' => $admin->id,
                ]);
            }
        }
        $items = Item::take(10)->get();

        // If no deceased, create some
        if (Deceased::count() === 0) {
            $zone = \App\Models\Zone::first();
            for ($i = 1; $i <= 20; $i++) {
                Deceased::create([
                    'first_name' => "DeceasedFirst $i",
                    'last_name' => "DeceasedLast $i",
                    'nin' => '123456789' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'reg_no' => 'DEC-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                    'guardian_name' => "Guardian $i",
                    'guardian_phone' => '080123456' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'vulnerability_status' => 'A',
                    'date_registered' => now(),
                    'zone_id' => $zone?->id,
                ]);
            }
        }
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
