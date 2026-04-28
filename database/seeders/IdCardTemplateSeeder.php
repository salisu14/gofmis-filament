<?php

namespace Database\Seeders;

use App\Models\IdCardTemplate;
use Illuminate\Database\Seeder;

class IdCardTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        IdCardTemplate::create([
            'name' => 'Standard Widow Card',
            'type' => 'widow',
            'layout_config' => [
                'primary_color' => '#8B4513',
                'secondary_color' => '#FFF8F0',
                'font_family' => 'Helvetica',
                'photo_size' => [16, 20], // mm
                'qr_size' => 16, // mm
            ],
            'is_active' => true,
        ]);

        IdCardTemplate::create([
            'name' => 'Standard Orphan Card',
            'type' => 'orphan',
            'layout_config' => [
                'primary_color' => '#1E90FF',
                'secondary_color' => '#F0F8FF',
                'font_family' => 'Helvetica',
                'photo_size' => [16, 20],
                'qr_size' => 16,
            ],
            'is_active' => true,
        ]);
    }
}
