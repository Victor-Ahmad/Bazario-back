<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        $users = [
            // Sellers

            [
                'name' => 'Ahmad Saleh',
                'email' => 'ahmad.seller@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],
            [
                'name' => 'Mona Ali',
                'email' => 'mona.seller@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],
            [
                'name' => 'Omar Youssef',
                'email' => 'omar.seller@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],
            [
                'name' => 'Nour Hassan',
                'email' => 'nour.seller@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],
            // ServiceProviders
            [
                'name' => 'Laila Khoury',
                'email' => 'laila.service_provider@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],
            [
                'name' => 'Samir Fadel',
                'email' => 'samir.service_provider@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],
            [
                'name' => 'Rana Mansour',
                'email' => 'rana.service_provider@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],
            [
                'name' => 'Khaled Jamal',
                'email' => 'khaled.service_provider@example.com',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'remember_token' => null,
            ],


            [
                'name' => 'Admin',
                'email' => 'migrate:fresh --seed',
                'email_verified_at' => now(),
                'password' => Hash::make('12345678'),
                'remember_token' => null,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
