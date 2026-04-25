<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Town;
use Illuminate\Support\Str;

class TownsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $city = City::first();

        $towns = [
            [
                'id' => Str::uuid(),
                'name' => 'Garko Central',
                'city_id' => $city->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Garko North',
                'city_id' => $city->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        Town::insert($towns);
    }
}
