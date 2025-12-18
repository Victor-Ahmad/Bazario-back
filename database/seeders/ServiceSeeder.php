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
                    'description' => 'Professional coverage for your wedding day.',
                    'category_slug' => 'photography',
                    'price' => 450.00,
                    'duration_minutes' => 180,
                    'location_type' => 'onsite',
                ],
                [
                    'title' => ['en' => 'Portrait Sessions', 'ar' => 'جلسات تصوير شخصية'],
                    'description' => 'Studio or outdoor portraits.',
                    'category_slug' => 'photography',
                    'price' => 120.00,
                    'duration_minutes' => 60,
                    'location_type' => 'onsite',
                ],
                [
                    'title' => ['en' => 'Product Photography', 'ar' => 'تصوير المنتجات'],
                    'description' => 'Crisp and clear product images.',
                    'category_slug' => 'photography',
                    'price' => 90.00,
                    'duration_minutes' => 60,
                    'location_type' => 'onsite',
                ],
                [
                    'title' => ['en' => 'Event Videography', 'ar' => 'تصوير فيديو للحفلات'],
                    'description' => 'Full HD video for events and celebrations.',
                    'category_slug' => 'photography',
                    'price' => 300.00,
                    'duration_minutes' => 120,
                    'location_type' => 'onsite',
                ],
            ],
            // Samir Fadel - Tutor
            'samir.service_provider@example.com' => [
                [
                    'title' => ['en' => 'Math Tutoring', 'ar' => 'دروس خصوصية في الرياضيات'],
                    'description' => 'High school & university math sessions.',
                    'category_slug' => 'tutoring',
                    'price' => 30.00,
                    'duration_minutes' => 60,
                    'location_type' => 'online',
                ],
                [
                    'title' => ['en' => 'Physics Tutoring', 'ar' => 'دروس خصوصية في الفيزياء'],
                    'description' => 'Understanding physics made easy.',
                    'category_slug' => 'tutoring',
                    'price' => 35.00,
                    'duration_minutes' => 60,
                    'location_type' => 'online',
                ],
                [
                    'title' => ['en' => 'Chemistry Tutoring', 'ar' => 'دروس خصوصية في الكيمياء'],
                    'description' => 'Interactive chemistry classes.',
                    'category_slug' => 'tutoring',
                    'price' => 32.00,
                    'duration_minutes' => 60,
                    'location_type' => 'online',
                ],
                [
                    'title' => ['en' => 'Exam Preparation', 'ar' => 'تحضير للامتحانات'],
                    'description' => 'Intensive sessions before exams.',
                    'category_slug' => 'tutoring',
                    'price' => 40.00,
                    'duration_minutes' => 90,
                    'location_type' => 'online',
                ],
            ],
            // Rana Mansour - Event Planner
            'rana.service_provider@example.com' => [
                [
                    'title' => ['en' => 'Wedding Planning', 'ar' => 'تنظيم حفلات الزفاف'],
                    'description' => 'Complete planning and coordination for your wedding.',
                    'category_slug' => 'event-planning',
                    'price' => 900.00,
                    'duration_minutes' => 300,
                    'location_type' => 'onsite',
                ],
                [
                    'title' => ['en' => 'Corporate Events', 'ar' => 'فعاليات الشركات'],
                    'description' => 'Corporate parties and events setup.',
                    'category_slug' => 'event-planning',
                    'price' => 650.00,
                    'duration_minutes' => 240,
                    'location_type' => 'onsite',
                ],
                [
                    'title' => ['en' => 'Birthday Parties', 'ar' => 'حفلات أعياد الميلاد'],
                    'description' => 'Fun, themed birthday parties for all ages.',
                    'category_slug' => 'event-planning',
                    'price' => 350.00,
                    'duration_minutes' => 180,
                    'location_type' => 'onsite',
                ],
                [
                    'title' => ['en' => 'Engagement Parties', 'ar' => 'حفلات الخطوبة'],
                    'description' => 'Plan your engagement party with style.',
                    'category_slug' => 'event-planning',
                    'price' => 500.00,
                    'duration_minutes' => 200,
                    'location_type' => 'onsite',
                ],
            ],
            // Khaled Jamal - Repair/Maintenance
            'khaled.service_provider@example.com' => [
                [
                    'title' => ['en' => 'Home Appliance Repair', 'ar' => 'تصليح الأجهزة المنزلية'],
                    'description' => 'Repair of all types of home appliances.',
                    'category_slug' => 'repair-maintenance',
                    'price' => 60.00,
                    'duration_minutes' => 90,
                    'location_type' => 'onsite',
                ],
                [
                    'title' => ['en' => 'Car Maintenance', 'ar' => 'صيانة السيارات'],
                    'description' => 'Routine and urgent car maintenance.',
                    'category_slug' => 'repair-maintenance',
                    'price' => 80.00,
                    'duration_minutes' => 120,
                    'location_type' => 'onsite',
                ],
                [
                    'title' => ['en' => 'Air Conditioner Service', 'ar' => 'خدمة المكيفات'],
                    'description' => 'Installation and maintenance of A/C units.',
                    'category_slug' => 'repair-maintenance',
                    'price' => 55.00,
                    'duration_minutes' => 70,
                    'location_type' => 'onsite',
                ],
                [
                    'title' => ['en' => 'Plumbing Services', 'ar' => 'خدمات السباكة'],
                    'description' => 'Plumbing repairs and installations.',
                    'category_slug' => 'repair-maintenance',
                    'price' => 40.00,
                    'duration_minutes' => 60,
                    'location_type' => 'onsite',
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
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}
