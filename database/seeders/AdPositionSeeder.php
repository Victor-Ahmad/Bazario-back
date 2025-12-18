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
                'name' => 'normal_ad',
                'label' => 'Normal Featured Ad (Moderate Visibility)',
                'priority' => 3,
            ],


        ];

        DB::table('ad_positions')->insert($positions);
    }
}
