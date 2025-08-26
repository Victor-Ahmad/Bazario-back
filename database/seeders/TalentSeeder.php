<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Talent;

class TalentSeeder extends Seeder
{
    public function run()
    {
        $talentEmails = [
            'laila.talent@example.com',
            'samir.talent@example.com',
            'rana.talent@example.com',
            'khaled.talent@example.com',
        ];

        $talentsData = [
            [
                'name' => 'Laila Khoury',
                'address' => 'Dubai, UAE',
                'logo' => null,
                'description' => 'Professional photographer for events and weddings',
                'status' => 'approved',
            ],
            [
                'name' => 'Samir Fadel',
                'address' => 'Riyadh, Saudi Arabia',
                'logo' => null,
                'description' => 'Private Math & Science Tutor',
                'status' => 'approved',
            ],
            [
                'name' => 'Rana Mansour',
                'address' => 'Istanbul, Turkey',
                'logo' => null,
                'description' => 'Event planner and coordinator',
                'status' => 'approved',
            ],
            [
                'name' => 'Khaled Jamal',
                'address' => 'Alexandria, Egypt',
                'logo' => null,
                'description' => 'Home repair and maintenance specialist',
                'status' => 'approved',
            ],
        ];

        foreach ($talentEmails as $index => $email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $talentData = $talentsData[$index];
                $talentData['user_id'] = $user->id;
                Talent::create($talentData);
            }
        }
    }
}
