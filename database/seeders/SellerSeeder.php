<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Seller;

class SellerSeeder extends Seeder
{
    public function run()
    {
        $sellerEmails = [
            'ahmad.seller@example.com',
            'mona.seller@example.com',
            'omar.seller@example.com',
            'nour.seller@example.com',
        ];

        $sellersData = [
            [
                'store_owner_name' => 'Ahmad Saleh',
                'store_name' => 'Ahmad Electronics',
                'address' => 'Damascus, Syria',
                'logo' => null,
                'description' => 'Best electronics in town',
                'status' => 'approved',
            ],
            [
                'store_owner_name' => 'Mona Ali',
                'store_name' => 'Mona Bookstore',
                'address' => 'Beirut, Lebanon',
                'logo' => null,
                'description' => 'All kinds of books and novels',
                'status' => 'approved',
            ],
            [
                'store_owner_name' => 'Omar Youssef',
                'store_name' => 'Omar Fashion',
                'address' => 'Cairo, Egypt',
                'logo' => null,
                'description' => 'Trendy clothing for all ages',
                'status' => 'approved',
            ],
            [
                'store_owner_name' => 'Nour Hassan',
                'store_name' => 'Nour Home Appliances',
                'address' => 'Amman, Jordan',
                'logo' => null,
                'description' => 'Home appliances and more',
                'status' => 'approved',
            ],
        ];

        foreach ($sellerEmails as $index => $email) {
            $user = User::where('email', $email)->first();
            if ($user) {
                $sellerData = $sellersData[$index];
                $sellerData['user_id'] = $user->id;
                Seller::create($sellerData);
            }
        }
    }
}
