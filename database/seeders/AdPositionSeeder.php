<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdPositionSeeder extends Seeder
{
    public function run()
    {

        $positions = [
            [
                'name' => 'golden_ad',
                'label' => 'Golden Featured Ad (Highest Visibility)',
                'priority' => 1,
            ],
            [
                'name' => 'silver_ad',
                'label' => 'Silver Featured Ad (High Visibility)',
                'priority' => 2,
            ],
            [
                'name' => 'bronze_ad',
                'label' => 'Bronze Featured Ad (Moderate Visibility)',
                'priority' => 3,
            ],
            [
                'name' => 'main_banner',
                'label' => 'Main Banner Ad',
                'priority' => 4,
            ],
            [
                'name' => 'sidebar_spotlight',
                'label' => 'Sidebar Spotlight Ad',
                'priority' => 5,
            ],
            [
                'name' => 'footer_ad',
                'label' => 'Footer Ad (Lowest Visibility)',
                'priority' => 6,
            ],
        ];

        DB::table('ad_positions')->insert($positions);
    }
}
