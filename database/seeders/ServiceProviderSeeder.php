<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ServiceProvider;

class ServiceProviderSeeder extends Seeder
{
    public function run()
    {
        $service_providerEmails = [
            'laila.service_provider@example.com',
            'samir.service_provider@example.com',
            'rana.service_provider@example.com',
            'khaled.service_provider@example.com',
        ];

        $service_providersData = [
            [
                'name' => 'Laila Khoury',
                'address' => 'Dubai, UAE',
                'logo' => null,
                'description' => 'Professional photographer for events and weddings',
                'timezone' => 'Asia/Dubai',
                'status' => 'approved',
            ],
            [
                'name' => 'Samir Fadel',
                'address' => 'Riyadh, Saudi Arabia',
                'logo' => null,
                'description' => 'Private Math & Science Tutor',
                'timezone' => 'Asia/Riyadh',
                'status' => 'approved',
            ],
            [
                'name' => 'Rana Mansour',
                'address' => 'Istanbul, Turkey',
                'logo' => null,
                'description' => 'Event planner and coordinator',
                'timezone' => 'Europe/Istanbul',
                'status' => 'approved',
            ],
            [
                'name' => 'Khaled Jamal',
                'address' => 'Alexandria, Egypt',
                'logo' => null,
                'description' => 'Home repair and maintenance specialist',
                'timezone' => 'Africa/Cairo',
                'status' => 'approved',
            ],
        ];

        foreach ($service_providerEmails as $index => $email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $service_providerData = $service_providersData[$index];
                $service_providerData['user_id'] = $user->id;
                ServiceProvider::create($service_providerData);
            }
        }
    }
}
