<?php

namespace Database\Seeders;

use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StatesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $states = [
            [
                'id' => Str::uuid(),
                'name' => 'Kano',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        State::insert($states);
    }
}
