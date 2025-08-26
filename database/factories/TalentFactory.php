<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TalentFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->name,
            'address' => $this->faker->address,
            'logo' => $this->faker->imageUrl(200, 200, 'people', true),
            'description' => $this->faker->sentence,
            'status' => 'approved',
        ];
    }
}
