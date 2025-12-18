<?php

namespace Database\Factories;

use App\Models\ServiceProvider;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ServiceFactory extends Factory
{
    public function definition()
    {
        $title = [
            'en' => $this->faker->words(3, true),
            'ar' => $this->faker->words(3, true),
        ];
        return [
            'provider_id' => ServiceProvider::inRandomOrder()->first()?->id ?? ServiceProvider::factory(),
            'category_id' => Category::inRandomOrder()->first()?->id ?? Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title['en'] . '-' . $this->faker->unique()->randomNumber()),
            'description' => $this->faker->paragraph,
            'price' => $this->faker->randomFloat(2, 20, 1000),
            'currency_iso' => $this->faker->currencyCode,
            'duration_minutes' => $this->faker->numberBetween(30, 240),
            'location_type' => $this->faker->randomElement(['online', 'onsite']),
            'is_active' => true,
        ];
    }
}
