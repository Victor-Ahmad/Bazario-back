<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceProvider;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    public function run()
    {
        $categories = Category::where('type', 'service')->pluck('id', 'slug');

        $servicesByProvider = [
            // Laila Khoury - Photographer
            'laila.service_provider@example.com' => [
                [
                    'title' => ['en' => 'Wedding Photography', 'ar' => 'تصوير حفلات الزفاف'],
                    'description' => [
                        'en' => 'Professional coverage for your wedding day.',
                        'ar' => 'تغطية احترافية ليوم زفافك.',
                    ],
                    'category_slug' => 'photography',
                    'price' => 450.00,
                    'duration_minutes' => 180,
                    'location_type' => 'on_site',
                    'slot_interval_minutes' => 30,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Portrait Sessions', 'ar' => 'جلسات تصوير شخصية'],
                    'description' => [
                        'en' => 'Studio or outdoor portraits.',
                        'ar' => 'جلسات تصوير داخل الاستوديو أو في الهواء الطلق.',
                    ],
                    'category_slug' => 'photography',
                    'price' => 120.00,
                    'duration_minutes' => 60,
                    'location_type' => 'on_site',
                    'slot_interval_minutes' => 15,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Product Photography', 'ar' => 'تصوير المنتجات'],
                    'description' => [
                        'en' => 'Crisp and clear product images.',
                        'ar' => 'صور منتجات واضحة وعالية الجودة.',
                    ],
                    'category_slug' => 'photography',
                    'price' => 90.00,
                    'duration_minutes' => 60,
                    'location_type' => 'on_site',
                    'slot_interval_minutes' => 15,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Event Videography', 'ar' => 'تصوير فيديو للحفلات'],
                    'description' => [
                        'en' => 'Full HD video for events and celebrations.',
                        'ar' => 'تصوير فيديو عالي الدقة للحفلات والمناسبات.',
                    ],
                    'category_slug' => 'photography',
                    'price' => 300.00,
                    'duration_minutes' => 120,
                    'location_type' => 'on_site',
                    'slot_interval_minutes' => 30,
                    'max_concurrent_bookings' => 1,
                ],
            ],
            // Samir Fadel - Tutor
            'samir.service_provider@example.com' => [
                [
                    'title' => ['en' => 'Math Tutoring', 'ar' => 'دروس خصوصية في الرياضيات'],
                    'description' => [
                        'en' => 'High school & university math sessions.',
                        'ar' => 'جلسات رياضيات للمدرسة والجامعة.',
                    ],
                    'category_slug' => 'tutoring',
                    'price' => 30.00,
                    'duration_minutes' => 60,
                    'location_type' => 'remote',
                    'slot_interval_minutes' => 15,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Physics Tutoring', 'ar' => 'دروس خصوصية في الفيزياء'],
                    'description' => [
                        'en' => 'Understanding physics made easy.',
                        'ar' => 'شرح مبسط للفيزياء.',
                    ],
                    'category_slug' => 'tutoring',
                    'price' => 35.00,
                    'duration_minutes' => 60,
                    'location_type' => 'remote',
                    'slot_interval_minutes' => 15,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Chemistry Tutoring', 'ar' => 'دروس خصوصية في الكيمياء'],
                    'description' => [
                        'en' => 'Interactive chemistry classes.',
                        'ar' => 'حصص كيمياء تفاعلية.',
                    ],
                    'category_slug' => 'tutoring',
                    'price' => 32.00,
                    'duration_minutes' => 60,
                    'location_type' => 'remote',
                    'slot_interval_minutes' => 15,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Exam Preparation', 'ar' => 'تحضير للامتحانات'],
                    'description' => [
                        'en' => 'Intensive sessions before exams.',
                        'ar' => 'جلسات مكثفة قبل الامتحانات.',
                    ],
                    'category_slug' => 'tutoring',
                    'price' => 40.00,
                    'duration_minutes' => 90,
                    'location_type' => 'remote',
                    'slot_interval_minutes' => 30,
                    'max_concurrent_bookings' => 1,
                ],
            ],
            // Rana Mansour - Event Planner
            'rana.service_provider@example.com' => [
                [
                    'title' => ['en' => 'Wedding Planning', 'ar' => 'تنظيم حفلات الزفاف'],
                    'description' => [
                        'en' => 'Complete planning and coordination for your wedding.',
                        'ar' => 'تنظيم كامل وتنسيق لحفل الزفاف.',
                    ],
                    'category_slug' => 'event-planning',
                    'price' => 900.00,
                    'duration_minutes' => 300,
                    'location_type' => 'on_site',
                    'slot_interval_minutes' => 60,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Corporate Events', 'ar' => 'فعاليات الشركات'],
                    'description' => [
                        'en' => 'Corporate parties and events setup.',
                        'ar' => 'تنظيم فعاليات وحفلات الشركات.',
                    ],
                    'category_slug' => 'event-planning',
                    'price' => 650.00,
                    'duration_minutes' => 240,
                    'location_type' => 'on_site',
                    'slot_interval_minutes' => 60,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Birthday Parties', 'ar' => 'حفلات أعياد الميلاد'],
                    'description' => [
                        'en' => 'Fun, themed birthday parties for all ages.',
                        'ar' => 'حفلات أعياد ميلاد ممتعة لجميع الأعمار.',
                    ],
                    'category_slug' => 'event-planning',
                    'price' => 350.00,
                    'duration_minutes' => 180,
                    'location_type' => 'on_site',
                    'slot_interval_minutes' => 30,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Engagement Parties', 'ar' => 'حفلات الخطوبة'],
                    'description' => [
                        'en' => 'Plan your engagement party with style.',
                        'ar' => 'تنظيم حفلات الخطوبة بأسلوب مميز.',
                    ],
                    'category_slug' => 'event-planning',
                    'price' => 500.00,
                    'duration_minutes' => 200,
                    'location_type' => 'on_site',
                    'slot_interval_minutes' => 30,
                    'max_concurrent_bookings' => 1,
                ],
            ],
            // Khaled Jamal - Repair/Maintenance
            'khaled.service_provider@example.com' => [
                [
                    'title' => ['en' => 'Home Appliance Repair', 'ar' => 'تصليح الأجهزة المنزلية'],
                    'description' => [
                        'en' => 'Repair of all types of home appliances.',
                        'ar' => 'تصليح جميع أنواع الأجهزة المنزلية.',
                    ],
                    'category_slug' => 'repair-maintenance',
                    'price' => 60.00,
                    'duration_minutes' => 90,
                    'location_type' => 'at_customer',
                    'slot_interval_minutes' => 30,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Car Maintenance', 'ar' => 'صيانة السيارات'],
                    'description' => [
                        'en' => 'Routine and urgent car maintenance.',
                        'ar' => 'صيانة دورية وسريعة للسيارات.',
                    ],
                    'category_slug' => 'repair-maintenance',
                    'price' => 80.00,
                    'duration_minutes' => 120,
                    'location_type' => 'on_site',
                    'slot_interval_minutes' => 30,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Air Conditioner Service', 'ar' => 'خدمة المكيفات'],
                    'description' => [
                        'en' => 'Installation and maintenance of A/C units.',
                        'ar' => 'تركيب وصيانة وحدات التكييف.',
                    ],
                    'category_slug' => 'repair-maintenance',
                    'price' => 55.00,
                    'duration_minutes' => 70,
                    'location_type' => 'on_site',
                    'slot_interval_minutes' => 30,
                    'max_concurrent_bookings' => 1,
                ],
                [
                    'title' => ['en' => 'Plumbing Services', 'ar' => 'خدمات السباكة'],
                    'description' => [
                        'en' => 'Plumbing repairs and installations.',
                        'ar' => 'إصلاحات وتركيبات السباكة.',
                    ],
                    'category_slug' => 'repair-maintenance',
                    'price' => 40.00,
                    'duration_minutes' => 60,
                    'location_type' => 'at_customer',
                    'slot_interval_minutes' => 15,
                    'max_concurrent_bookings' => 1,
                ],
            ],
        ];

        foreach ($servicesByProvider as $email => $services) {
            $service_provider = ServiceProvider::whereHas('user', function ($query) use ($email) {
                $query->where('email', $email);
            })->first();

            if ($service_provider) {
                foreach ($services as $serviceData) {
                    $title = $serviceData['title'];
                    Service::create([
                        'provider_id' => $service_provider->id,
                        'category_id' => $categories[$serviceData['category_slug']] ?? null,
                        'title' => $title,
                        'slug' => Str::slug($title['en'] . '-' . uniqid()),
                        'description' => $serviceData['description'],
                        'price' => $serviceData['price'],
                        'currency_iso' => 'USD',
                        'duration_minutes' => $serviceData['duration_minutes'],
                        'location_type' => $serviceData['location_type'],
                        'slot_interval_minutes' => $serviceData['slot_interval_minutes'] ?? 15,
                        'max_concurrent_bookings' => $serviceData['max_concurrent_bookings'] ?? 1,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
