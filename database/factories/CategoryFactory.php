<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CategoryFactory extends Factory
{
    public function definition()
    {
        $name = [
            'en' => $this->faker->word,
            'ar' => $this->faker->word,
        ];
        return [
            'name' => $name,
            'image' => $this->faker->imageUrl(200, 200, 'category', true),
            'parent_id' => null,
            'slug' => Str::slug($name['en'] . '-' . $this->faker->unique()->randomNumber()),
            'description' => $this->faker->sentence,
            'type' => $this->faker->randomElement(['product', 'service', 'other']),
        ];
    }
}
