<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class RoleUserTableSeeder extends Seeder
{
    public function run()
    {
        User::where('email', 'sadmin@admin.com')->first()?->syncRoles('super_admin');
    }
}
