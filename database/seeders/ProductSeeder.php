<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Seller;
use App\Models\Category;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run()
    {
        // Match sellers to categories for product realism
        $categories = Category::where('type', 'product')->pluck('id', 'slug');

        $productsBySeller = [
            // Ahmad Electronics
            'ahmad.seller@example.com' => [
                [
                    'name' => ['en' => 'Smartphone X200', 'ar' => 'هاتف X200'],
                    'description' => ['en' => 'Latest smartphone with amazing features.', 'ar' => 'أحدث هاتف مع ميزات رائعة.'],
                    'category_slug' => 'electronics',
                    'price' => 399.99,
                ],
                [
                    'name' => ['en' => 'Bluetooth Headphones', 'ar' => 'سماعات بلوتوث'],
                    'description' => ['en' => 'Wireless, long battery life.', 'ar' => 'لاسلكي وعمر بطارية طويل.'],
                    'category_slug' => 'electronics',
                    'price' => 59.99,
                ],
                [
                    'name' => ['en' => 'Laptop Pro 15"', 'ar' => 'حاسوب محمول 15"'],
                    'description' => ['en' => 'High performance laptop for work and play.', 'ar' => 'حاسوب عالي الأداء للعمل والترفيه.'],
                    'category_slug' => 'electronics',
                    'price' => 850.00,
                ],
                [
                    'name' => ['en' => 'Kitchen Blender', 'ar' => 'خلاط مطبخ'],
                    'description' => ['en' => 'Powerful blender for all uses.', 'ar' => 'خلاط قوي لجميع الاستخدامات.'],
                    'category_slug' => 'home-appliances',
                    'price' => 74.50,
                ],
            ],
            // Mona Bookstore
            'mona.seller@example.com' => [
                [
                    'name' => ['en' => 'The Great Gatsby', 'ar' => 'جاتسبي العظيم'],
                    'description' => ['en' => 'Classic American novel.', 'ar' => 'رواية أمريكية كلاسيكية.'],
                    'category_slug' => 'books',
                    'price' => 12.90,
                ],
                [
                    'name' => ['en' => 'Arabic Poetry', 'ar' => 'شعر عربي'],
                    'description' => ['en' => 'A collection of Arabic poems.', 'ar' => 'مجموعة من القصائد العربية.'],
                    'category_slug' => 'books',
                    'price' => 9.99,
                ],
                [
                    'name' => ['en' => 'Children\'s Story Book', 'ar' => 'كتاب قصص للأطفال'],
                    'description' => ['en' => 'Stories for children ages 5–8.', 'ar' => 'قصص للأطفال من سن 5-8.'],
                    'category_slug' => 'books',
                    'price' => 8.00,
                ],
                [
                    'name' => ['en' => 'Travel Guide to Lebanon', 'ar' => 'دليل السفر إلى لبنان'],
                    'description' => ['en' => 'Explore Lebanon\'s beautiful sites.', 'ar' => 'اكتشف أجمل مواقع لبنان.'],
                    'category_slug' => 'books',
                    'price' => 14.99,
                ],
            ],
            // Omar Fashion
            'omar.seller@example.com' => [
                [
                    'name' => ['en' => 'Men\'s Classic Shirt', 'ar' => 'قميص رجالي كلاسيكي'],
                    'description' => ['en' => 'Elegant cotton shirt.', 'ar' => 'قميص قطني أنيق.'],
                    'category_slug' => 'clothing',
                    'price' => 27.00,
                ],
                [
                    'name' => ['en' => 'Women\'s Summer Dress', 'ar' => 'فستان صيفي نسائي'],
                    'description' => ['en' => 'Lightweight and fashionable.', 'ar' => 'خفيف وعصري.'],
                    'category_slug' => 'clothing',
                    'price' => 34.50,
                ],
                [
                    'name' => ['en' => 'Kids Denim Jacket', 'ar' => 'سترة دنيم للأطفال'],
                    'description' => ['en' => 'Durable denim for kids.', 'ar' => 'دنيم متين للأطفال.'],
                    'category_slug' => 'clothing',
                    'price' => 18.75,
                ],
                [
                    'name' => ['en' => 'Leather Handbag', 'ar' => 'حقيبة يد جلدية'],
                    'description' => ['en' => 'Stylish leather bag for women.', 'ar' => 'حقيبة أنيقة للنساء.'],
                    'category_slug' => 'clothing',
                    'price' => 55.00,
                ],
                [
                    'name' => ['en' => 'Casual Sneakers', 'ar' => 'أحذية رياضية عادية'],
                    'description' => ['en' => 'Comfortable sneakers for daily wear.', 'ar' => 'أحذية مريحة للاستخدام اليومي.'],
                    'category_slug' => 'clothing',
                    'price' => 39.00,
                ],
            ],
            // Nour Home Appliances
            'nour.seller@example.com' => [
                [
                    'name' => ['en' => 'Vacuum Cleaner', 'ar' => 'مكنسة كهربائية'],
                    'description' => ['en' => 'Efficient and silent vacuum cleaner.', 'ar' => 'مكنسة فعالة وهادئة.'],
                    'category_slug' => 'home-appliances',
                    'price' => 120.00,
                ],
                [
                    'name' => ['en' => 'Microwave Oven', 'ar' => 'فرن ميكروويف'],
                    'description' => ['en' => 'Digital microwave oven.', 'ar' => 'فرن ميكروويف رقمي.'],
                    'category_slug' => 'home-appliances',
                    'price' => 89.00,
                ],
                [
                    'name' => ['en' => 'Iron & Steamer', 'ar' => 'مكواة وبخار'],
                    'description' => ['en' => 'For perfect clothes care.', 'ar' => 'للعناية المثالية بالملابس.'],
                    'category_slug' => 'home-appliances',
                    'price' => 44.90,
                ],
                [
                    'name' => ['en' => 'Board Game: Chess', 'ar' => 'لعبة شطرنج'],
                    'description' => ['en' => 'Wooden chess set.', 'ar' => 'مجموعة شطرنج خشبية.'],
                    'category_slug' => 'toys-games',
                    'price' => 24.00,
                ],
            ],
        ];

        foreach ($productsBySeller as $email => $products) {
            $seller = Seller::whereHas('user', function ($query) use ($email) {
                $query->where('email', $email);
            })->first();

            if ($seller) {
                foreach ($products as $productData) {
                    Product::create([
                        'name' => $productData['name'],
                        'description' => $productData['description'],
                        'category_id' => $categories[$productData['category_slug']] ?? null,
                        'price' => $productData['price'],
                        'seller_id' => $seller->id,
                    ]);
                }
            }
        }
    }
}
