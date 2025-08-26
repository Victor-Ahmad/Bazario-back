<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Ad;
use App\Models\Seller;
use App\Models\Talent;
use App\Models\AdPosition;
use Carbon\Carbon;

class AdsSeeder extends Seeder
{
    public function run()
    {
        // Get all positions
        $positions = AdPosition::pluck('id', 'name');

        // Get sellers and talents
        $sellers = Seller::all();
        $talents = Talent::all();

        $today = Carbon::create(2025, 7, 27);

        // Helper to get random date between now and 2025-12-31
        function randomExpiry($today)
        {
            if (rand(0, 1)) {
                return null;
            }
            return $today->copy()->addDays(rand(5, 120))->format('Y-m-d H:i:s');
        }

        // Sample ads for sellers
        foreach ($sellers as $index => $seller) {
            Ad::create([
                'title' => 'Big Sale on ' . $seller->store_name,
                'subtitle' => 'Up to ' . (20 + $index * 10) . '% off!',
                'expires_at' => randomExpiry($today),
                'adable_type' => Seller::class,
                'adable_id' => $seller->id,
                'ad_position_id' => $positions['golden_ad'],
                'status' => 'approved',
            ]);
            Ad::create([
                'title' => $seller->store_name . ' New Arrivals',
                'subtitle' => 'Check our fresh collection.',
                'expires_at' => randomExpiry($today),
                'adable_type' => Seller::class,
                'adable_id' => $seller->id,
                'ad_position_id' => $positions['silver_ad'],
                'status' => 'pending',
            ]);
        }

        // Sample ads for talents
        foreach ($talents as $index => $talent) {
            Ad::create([
                'title' => $talent->name . ' – Book Now!',
                'subtitle' => 'Special offer for this month.',
                'expires_at' => randomExpiry($today),
                'adable_type' => Talent::class,
                'adable_id' => $talent->id,
                'ad_position_id' => $positions['main_banner'],
                'status' => 'approved',
            ]);
            Ad::create([
                'title' => $talent->name . '\'s Exclusive Service',
                'subtitle' => null,
                'expires_at' => randomExpiry($today),
                'adable_type' => Talent::class,
                'adable_id' => $talent->id,
                'ad_position_id' => $positions['sidebar_spotlight'],
                'status' => 'pending',
            ]);
        }

        // A few extra ads, mixing it up with other ad positions
        $allAdables = $sellers->concat($talents);
        $extraTitles = [
            'Limited Time Offer!',
            'Seasonal Discount',
            'Get Ready for Holidays',
            'Exclusive Only Today',
            'Special for Our Customers',
        ];
        $otherPositions = [
            'bronze_ad',
            'footer_ad'
        ];
        foreach ($extraTitles as $i => $title) {
            $adable = $allAdables->get($i % $allAdables->count());
            Ad::create([
                'title' => $title,
                'subtitle' => 'Save big before ' . Carbon::create(2025, 12, 31)->format('M d'),
                'expires_at' => randomExpiry($today),
                'adable_type' => get_class($adable),
                'adable_id' => $adable->id,
                'ad_position_id' => $positions[$otherPositions[$i % count($otherPositions)]],
                'status' => 'approved',
            ]);
        }
    }
}
