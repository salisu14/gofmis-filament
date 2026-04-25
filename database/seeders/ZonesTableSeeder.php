<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Zone;
use Illuminate\Support\Str;


class ZonesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $zones = [];
        $now = now();

        // Generate A1-A20
        foreach (range(1, 20) as $i) {
            $zones[] = [
                'id' => Str::uuid(),
                'name' => "A{$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Generate B1-B5
        foreach (range(1, 5) as $i) {
            $zones[] = [
                'id' => Str::uuid(),
                'name' => "B{$i}",
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Zone::insert($zones);
    }
}
