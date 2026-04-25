<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\City;
use Illuminate\Support\Str;

class CitiesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $state = State::first();

        $cities = [
            [
                'id' => Str::uuid(),
                'name' => 'Garko',
                'state_id' =>  $state->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        City::insert($cities);
    }
}
