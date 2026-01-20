<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Seller;
use App\Models\ServiceProvider;
use Spatie\Permission\Models\Role;

class ApprovedAccountRolesSeeder extends Seeder
{
    public function run(): void
    {
        $customerRole = Role::where('name', 'customer')
            ->where('guard_name', 'web')
            ->first();
        $sellerRole = Role::where('name', 'seller')
            ->where('guard_name', 'web')
            ->first();
        $providerRole = Role::where('name', 'service_provider')
            ->where('guard_name', 'web')
            ->first();

        if ($sellerRole && $customerRole) {
            Seller::with('user')
                ->where('status', 'approved')
                ->get()
                ->each(function ($seller) use ($sellerRole, $customerRole) {
                    $user = $seller->user;
                    if (!$user) return;
                    if (!$user->hasRole($sellerRole)) {
                        $user->assignRole($sellerRole);
                    }
                    if (!$user->hasRole($customerRole)) {
                        $user->assignRole($customerRole);
                    }
                });
        }

        if ($providerRole && $customerRole) {
            ServiceProvider::with('user')
                ->where('status', 'approved')
                ->get()
                ->each(function ($provider) use ($providerRole, $customerRole) {
                    $user = $provider->user;
                    if (!$user) return;
                    if (!$user->hasRole($providerRole)) {
                        $user->assignRole($providerRole);
                    }
                    if (!$user->hasRole($customerRole)) {
                        $user->assignRole($customerRole);
                    }
                });
        }
    }
}
