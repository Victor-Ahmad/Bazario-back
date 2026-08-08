<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\AdPosition;
use App\Models\Listing;
use App\Models\Product;
use App\Models\Seller;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AdsSeeder extends Seeder
{
    public function run()
    {
        $positions = AdPosition::pluck('id', 'name');
        $seedBase = Carbon::create(2026, 8, 8, 12, 0, 0);

        $sellers = Seller::query()
            ->with('user')
            ->get()
            ->keyBy(fn(Seller $seller) => $seller->user?->email);

        $serviceProviders = ServiceProvider::query()
            ->with('user')
            ->get()
            ->keyBy(fn(ServiceProvider $provider) => $provider->user?->email);

        $products = Product::query()
            ->whereHas('seller.user')
            ->with('seller.user')
            ->get()
            ->keyBy(fn(Product $product) => ($product->seller?->user?->email ?? 'unknown') . '|' . $this->englishText($product->name_translations));

        $services = Service::query()
            ->whereHas('serviceProvider.user')
            ->with('serviceProvider.user')
            ->get()
            ->keyBy(fn(Service $service) => ($service->serviceProvider?->user?->email ?? 'unknown') . '|' . $this->englishText($service->title_translations));

        $approvedAds = [
            [
                'title' => 'Ahmad Electronics summer offers',
                'subtitle' => 'Phones, laptops, and home devices from our current catalog.',
                'price' => 249.00,
                'expires_at' => $seedBase->copy()->addDays(45),
                'position' => 'golden_ad',
                'status' => 'approved',
                'adable_type' => Seller::class,
                'adable_id' => $sellers['ahmad.seller@example.com']?->id,
            ],
            [
                'title' => 'Wedding photography bookings open',
                'subtitle' => 'Professional full-day coverage for weddings and special events.',
                'price' => 349.00,
                'expires_at' => $seedBase->copy()->addDays(60),
                'position' => 'golden_ad',
                'status' => 'approved',
                'adable_type' => Service::class,
                'adable_id' => $services->get('laila.service_provider@example.com|Wedding Photography')?->id,
            ],
            [
                'title' => 'Corporate event planning now booking',
                'subtitle' => 'High-visibility planning support for conferences, launches, and company events.',
                'price' => 279.00,
                'expires_at' => $seedBase->copy()->addDays(55),
                'position' => 'golden_ad',
                'status' => 'approved',
                'adable_type' => Service::class,
                'adable_id' => $services->get('rana.service_provider@example.com|Corporate Events')?->id,
            ],
            [
                'title' => 'Laptop Pro 15 for work and study',
                'subtitle' => 'A premium laptop offer from approved marketplace electronics stock.',
                'price' => 229.00,
                'expires_at' => $seedBase->copy()->addDays(24),
                'position' => 'golden_ad',
                'status' => 'approved',
                'adable_type' => Product::class,
                'adable_id' => $products->get('ahmad.seller@example.com|Laptop Pro 15"')?->id,
            ],
            [
                'title' => 'Smartphone X200 available now',
                'subtitle' => 'A solid everyday phone with current marketplace stock.',
                'price' => 159.00,
                'expires_at' => $seedBase->copy()->addDays(21),
                'position' => 'silver_ad',
                'status' => 'approved',
                'adable_type' => Product::class,
                'adable_id' => $products->get('ahmad.seller@example.com|Smartphone X200')?->id,
            ],
            [
                'title' => 'Portrait sessions this month',
                'subtitle' => 'Studio and outdoor portrait bookings with flexible time slots.',
                'price' => 129.00,
                'expires_at' => $seedBase->copy()->addDays(30),
                'position' => 'silver_ad',
                'status' => 'approved',
                'adable_type' => Service::class,
                'adable_id' => $services->get('laila.service_provider@example.com|Portrait Sessions')?->id,
            ],
            [
                'title' => 'Omar Fashion new arrivals',
                'subtitle' => 'Seasonal clothing and accessories added to the store.',
                'price' => 119.00,
                'expires_at' => $seedBase->copy()->addDays(35),
                'position' => 'silver_ad',
                'status' => 'approved',
                'adable_type' => Seller::class,
                'adable_id' => $sellers['omar.seller@example.com']?->id,
            ],
            [
                'title' => 'Math tutoring this month',
                'subtitle' => 'Remote sessions for high school and university students with flexible scheduling.',
                'price' => 98.00,
                'expires_at' => $seedBase->copy()->addDays(27),
                'position' => 'silver_ad',
                'status' => 'approved',
                'adable_type' => Service::class,
                'adable_id' => $services->get('samir.service_provider@example.com|Math Tutoring')?->id,
            ],
            [
                'title' => 'Vacuum cleaner household offer',
                'subtitle' => 'A practical home pick for apartments, studios, and family homes.',
                'price' => 88.00,
                'expires_at' => $seedBase->copy()->addDays(34),
                'position' => 'silver_ad',
                'status' => 'approved',
                'adable_type' => Product::class,
                'adable_id' => $products->get('nour.seller@example.com|Vacuum Cleaner')?->id,
            ],
            [
                'title' => 'Laptop Pro 15" in stock',
                'subtitle' => 'A work-ready laptop for study, office use, and everyday tasks.',
                'price' => 79.00,
                'expires_at' => $seedBase->copy()->addDays(18),
                'position' => 'normal_ad',
                'status' => 'approved',
                'adable_type' => Product::class,
                'adable_id' => $products->get('ahmad.seller@example.com|Laptop Pro 15"')?->id,
            ],
            [
                'title' => 'Product photography service',
                'subtitle' => 'Clean product shots for catalogs, menus, and online stores.',
                'price' => 69.00,
                'expires_at' => $seedBase->copy()->addDays(28),
                'position' => 'normal_ad',
                'status' => 'approved',
                'adable_type' => Service::class,
                'adable_id' => $services->get('laila.service_provider@example.com|Product Photography')?->id,
            ],
            [
                'title' => 'Nour Home Appliances picks',
                'subtitle' => 'Everyday home appliances and practical household items.',
                'price' => 59.00,
                'expires_at' => $seedBase->copy()->addDays(40),
                'position' => 'normal_ad',
                'status' => 'approved',
                'adable_type' => Seller::class,
                'adable_id' => $sellers['nour.seller@example.com']?->id,
            ],
            [
                'title' => 'Online tutoring with Samir Fadel',
                'subtitle' => 'Remote math and science sessions for school and university students.',
                'price' => 64.00,
                'expires_at' => $seedBase->copy()->addDays(32),
                'position' => 'normal_ad',
                'status' => 'approved',
                'adable_type' => ServiceProvider::class,
                'adable_id' => $serviceProviders['samir.service_provider@example.com']?->id,
            ],
            [
                'title' => 'Bluetooth headphones marketplace pick',
                'subtitle' => 'Wireless listening with long battery life from approved seller inventory.',
                'price' => 49.00,
                'expires_at' => $seedBase->copy()->addDays(19),
                'position' => 'normal_ad',
                'status' => 'approved',
                'adable_type' => Product::class,
                'adable_id' => $products->get('ahmad.seller@example.com|Bluetooth Headphones')?->id,
            ],
            [
                'title' => 'Physics tutoring remote sessions',
                'subtitle' => 'One-on-one remote study support with an approved provider.',
                'price' => 54.00,
                'expires_at' => $seedBase->copy()->addDays(23),
                'position' => 'normal_ad',
                'status' => 'approved',
                'adable_type' => Service::class,
                'adable_id' => $services->get('samir.service_provider@example.com|Physics Tutoring')?->id,
            ],
            [
                'title' => 'Event videography for celebrations',
                'subtitle' => 'Capture business events and family occasions with full HD coverage.',
                'price' => 84.00,
                'expires_at' => $seedBase->copy()->addDays(26),
                'position' => 'normal_ad',
                'status' => 'approved',
                'adable_type' => Service::class,
                'adable_id' => $services->get('laila.service_provider@example.com|Event Videography')?->id,
            ],
            [
                'title' => 'Corporate event planning',
                'subtitle' => 'Planning and coordination support for company events and internal functions.',
                'price' => 135.00,
                'expires_at' => $seedBase->copy()->addDays(50),
                'position' => 'silver_ad',
                'status' => 'pending',
                'adable_type' => Service::class,
                'adable_id' => $services->get('rana.service_provider@example.com|Corporate Events')?->id,
            ],
        ];

        foreach ($approvedAds as $attributes) {
            if (!$attributes['adable_id']) {
                continue;
            }

            Ad::updateOrCreate(
                ['title' => $attributes['title']],
                [
                    'subtitle' => $attributes['subtitle'],
                    'price' => $attributes['price'],
                    'expires_at' => $attributes['expires_at'],
                    'status' => $attributes['status'],
                    'adable_type' => $attributes['adable_type'],
                    'adable_id' => $attributes['adable_id'],
                    'ad_position_id' => $positions[$attributes['position']] ?? null,
                ]
            );
        }

        $announcementOwner = User::where('email', 'yara.customer@example.com')->first()
            ?? User::where('email', 'ahmad.seller@example.com')->first();

        if (!$announcementOwner) {
            return;
        }

        $announcementSeeds = [
            [
                'listing' => [
                    'title' => 'Marketplace update: electronics and home devices',
                    'description' => 'This week we are highlighting selected electronics and home appliance listings from approved marketplace sellers.',
                    'price' => null,
                    'attributes' => ['kind' => 'announcement', 'tier' => 'gold'],
                ],
                'ad' => [
                    'title' => 'Marketplace update: electronics and home devices',
                    'subtitle' => 'Current marketplace picks from electronics and home categories.',
                    'position' => 'golden_ad',
                    'expires_at' => $seedBase->copy()->addDays(25),
                ],
            ],
            [
                'listing' => [
                    'title' => 'Marketplace update: service bookings this week',
                    'description' => 'Approved providers have opened new availability for photography, tutoring, and event-related services.',
                    'price' => null,
                    'attributes' => ['kind' => 'announcement', 'tier' => 'silver'],
                ],
                'ad' => [
                    'title' => 'Marketplace update: service bookings this week',
                    'subtitle' => 'New service availability is now visible across the marketplace.',
                    'position' => 'silver_ad',
                    'expires_at' => $seedBase->copy()->addDays(20),
                ],
            ],
            [
                'listing' => [
                    'title' => 'Marketplace update: home essentials and services',
                    'description' => 'A quick marketplace note covering practical products and useful service offers from approved accounts.',
                    'price' => null,
                    'attributes' => ['kind' => 'announcement', 'tier' => 'normal'],
                ],
                'ad' => [
                    'title' => 'Marketplace update: home essentials and services',
                    'subtitle' => 'Useful offers and business updates from approved marketplace members.',
                    'position' => 'normal_ad',
                    'expires_at' => $seedBase->copy()->addDays(15),
                ],
            ],
        ];

        foreach ($announcementSeeds as $seed) {
            $listing = Listing::updateOrCreate(
                [
                    'user_id' => $announcementOwner->id,
                    'title' => $seed['listing']['title'],
                ],
                [
                    'description' => $seed['listing']['description'],
                    'price' => $seed['listing']['price'],
                    'attributes' => $seed['listing']['attributes'],
                ]
            );

            Ad::updateOrCreate(
                ['title' => $seed['ad']['title']],
                [
                    'subtitle' => $seed['ad']['subtitle'],
                    'price' => null,
                    'expires_at' => $seed['ad']['expires_at'],
                    'status' => 'approved',
                    'adable_type' => Listing::class,
                    'adable_id' => $listing->id,
                    'ad_position_id' => $positions[$seed['ad']['position']] ?? null,
                ]
            );
        }
    }

    private function englishText($translations): string
    {
        if (!is_array($translations)) {
            return '';
        }

        return (string) ($translations['en'] ?? '');
    }
}
