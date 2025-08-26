<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            RolesTableSeeder::class,
            AdminSeeder::class,
            CategorySeeder::class,
            UserSeeder::class,
            SellerSeeder::class,
            TalentSeeder::class,
            ProductSeeder::class,
            ServiceSeeder::class,
            AdPositionSeeder::class,
            AdsSeeder::class,
        ]);
    }
}
