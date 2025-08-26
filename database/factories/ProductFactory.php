<?php

namespace Database\Factories;

use App\Models\Seller;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition()
    {
        $name = [
            'en' => $this->faker->word,
            'ar' => $this->faker->word,
        ];
        $desc = [
            'en' => $this->faker->sentence,
            'ar' => $this->faker->sentence,
        ];
        return [
            'name' => $name,
            'description' => $desc,
            'category_id' => Category::inRandomOrder()->first()?->id ?? Category::factory(),
            'price' => $this->faker->randomFloat(2, 10, 500),
            'seller_id' => Seller::inRandomOrder()->first()?->id ?? Seller::factory(),
        ];
    }
}
