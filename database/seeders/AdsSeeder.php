<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ad;
use App\Models\Seller;
use App\Models\ServiceProvider;
use App\Models\AdPosition;
use Carbon\Carbon;

class AdsSeeder extends Seeder
{
    public function run()
    {
        // Positions keyed by name: ['golden_ad' => 1, 'silver_ad' => 2, 'normal_ad' => 3]
        $positions = AdPosition::pluck('id', 'name');

        $sellers = Seller::all();
        $service_providers = ServiceProvider::all();

        $today = Carbon::create(2025, 7, 27);

        // Helpers
        $randomExpiry = function (Carbon $base) {
            // ~50% chance to be null
            if (rand(0, 1)) {
                return null;
            }
            return $base->copy()->addDays(rand(5, 120))->format('Y-m-d H:i:s');
        };

        $randomPrice = function () {
            // returns decimal like 9.99 .. 499.99
            // 30% chance of NULL (no price)
            if (rand(1, 10) <= 3) {
                return null;
            }
            return round(mt_rand(999, 49999) / 100, 2);
        };

        // Sample ads for sellers
        foreach ($sellers as $index => $seller) {
            Ad::create([
                'title'          => 'Big Sale on ' . $seller->store_name,
                'subtitle'       => 'Up to ' . (20 + $index * 10) . '% off!',
                'expires_at'     => $randomExpiry($today),
                'adable_type'    => Seller::class,
                'adable_id'      => $seller->id,
                'ad_position_id' => $positions['golden_ad'] ?? null,
                'status'         => 'approved',
                'price'          => $randomPrice(),
            ]);

            Ad::create([
                'title'          => $seller->store_name . ' New Arrivals',
                'subtitle'       => 'Check our fresh collection.',
                'expires_at'     => $randomExpiry($today),
                'adable_type'    => Seller::class,
                'adable_id'      => $seller->id,
                'ad_position_id' => $positions['silver_ad'] ?? null,
                'status'         => 'pending',
                'price'          => $randomPrice(),
            ]);
        }

        // Sample ads for service_providers
        foreach ($service_providers as $index => $service_provider) {
            Ad::create([
                'title'          => $service_provider->name . ' – Book Now!',
                'subtitle'       => 'Special offer for this month.',
                'expires_at'     => $randomExpiry($today),
                'adable_type'    => ServiceProvider::class,
                'adable_id'      => $service_provider->id,
                'ad_position_id' => $positions['golden_ad'] ?? null,
                'status'         => 'approved',
                'price'          => $randomPrice(),
            ]);

            Ad::create([
                'title'          => $service_provider->name . "'s Exclusive Service",
                'subtitle'       => null,
                'expires_at'     => $randomExpiry($today),
                'adable_type'    => ServiceProvider::class,
                'adable_id'      => $service_provider->id,
                'ad_position_id' => $positions['silver_ad'] ?? null,
                'status'         => 'pending',
                'price'          => $randomPrice(),
            ]);
        }

        // Extra mixed ads on "normal" position
        $allAdables = $sellers->concat($service_providers);
        if ($allAdables->isNotEmpty()) {
            $extraTitles = [
                'Limited Time Offer!',
                'Seasonal Discount',
                'Get Ready for Holidays',
                'Exclusive Only Today',
                'Special for Our Customers',
            ];
            foreach ($extraTitles as $i => $title) {
                $adable = $allAdables->get($i % $allAdables->count());
                Ad::create([
                    'title'          => $title,
                    'subtitle'       => 'Save big before ' . Carbon::create(2025, 12, 31)->format('M d'),
                    'expires_at'     => $randomExpiry($today),
                    'adable_type'    => get_class($adable),
                    'adable_id'      => $adable->id,
                    'ad_position_id' => $positions['normal_ad'] ?? null,
                    'status'         => 'approved',
                    'price'          => $randomPrice(),
                ]);
            }
        }
    }
}
