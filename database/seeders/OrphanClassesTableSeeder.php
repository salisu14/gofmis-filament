<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrphanClass;
use Illuminate\Support\Str;
use App\Models\User;

class OrphanClassesTableSeeder extends Seeder
{
    public function run()
    {
        $user = User::first();

        $classNames = [
            'Primary 1', 'Primary 2', 'Primary 3',
            'Primary 4', 'Primary 5', 'Primary 6',
            'JSS I', 'JSS II', 'JSS III',
            'SS I', 'SS II', 'SS III',
        ];

        $now = now();

        $classes = collect($classNames)->map(fn ($name) => [
            'id'         => Str::uuid(),
            'name'       => $name,
            'user_id'    => $user?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->toArray();

        OrphanClass::insert($classes);
    }
}
