<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceImageFactory extends Factory
{
    public function definition()
    {
        return [
            'service_id' => Service::inRandomOrder()->first()?->id ?? Service::factory(),
            'image' => $this->faker->imageUrl(400, 400, 'services', true),
        ];
    }
}
