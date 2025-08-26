<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            // --- Product Categories ---
            [
                'name' => ['en' => 'Electronics', 'ar' => 'إلكترونيات'],
                'image' => null,
                'parent_id' => null,
                'slug' => 'electronics',
                'description' => 'Phones, laptops, and electronic gadgets',
                'type' => 'product',
            ],
            [
                'name' => ['en' => 'Books', 'ar' => 'كتب'],
                'image' => null,
                'parent_id' => null,
                'slug' => 'books',
                'description' => 'Printed and digital books in various genres',
                'type' => 'product',
            ],
            [
                'name' => ['en' => 'Clothing', 'ar' => 'ملابس'],
                'image' => null,
                'parent_id' => null,
                'slug' => 'clothing',
                'description' => 'Men and women fashion and accessories',
                'type' => 'product',
            ],
            [
                'name' => ['en' => 'Home Appliances', 'ar' => 'أجهزة منزلية'],
                'image' => null,
                'parent_id' => null,
                'slug' => 'home-appliances',
                'description' => 'Appliances and tools for home use',
                'type' => 'product',
            ],
            [
                'name' => ['en' => 'Toys & Games', 'ar' => 'ألعاب'],
                'image' => null,
                'parent_id' => null,
                'slug' => 'toys-games',
                'description' => 'Toys, games, and kids entertainment',
                'type' => 'product',
            ],

            // --- Service Categories ---
            [
                'name' => ['en' => 'Photography', 'ar' => 'تصوير'],
                'image' => null,
                'parent_id' => null,
                'slug' => 'photography',
                'description' => 'Photography and videography services',
                'type' => 'service',
            ],
            [
                'name' => ['en' => 'Tutoring', 'ar' => 'دروس خصوصية'],
                'image' => null,
                'parent_id' => null,
                'slug' => 'tutoring',
                'description' => 'Private lessons and tutoring in all subjects',
                'type' => 'service',
            ],
            [
                'name' => ['en' => 'Event Planning', 'ar' => 'تنظيم حفلات'],
                'image' => null,
                'parent_id' => null,
                'slug' => 'event-planning',
                'description' => 'Events, weddings, and celebrations organization',
                'type' => 'service',
            ],
            [
                'name' => ['en' => 'Home Cleaning', 'ar' => 'تنظيف المنازل'],
                'image' => null,
                'parent_id' => null,
                'slug' => 'home-cleaning',
                'description' => 'House, office, and space cleaning services',
                'type' => 'service',
            ],
            [
                'name' => ['en' => 'Repair & Maintenance', 'ar' => 'صيانة وإصلاح'],
                'image' => null,
                'parent_id' => null,
                'slug' => 'repair-maintenance',
                'description' => 'Repair and maintenance of devices, cars, etc.',
                'type' => 'service',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
