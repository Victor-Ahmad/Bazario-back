<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SellerFactory extends Factory
{
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'store_owner_name' => $this->faker->name,
            'store_name' => $this->faker->company . ' Store',
            'address' => $this->faker->address,
            'logo' => $this->faker->imageUrl(200, 200, 'business', true),
            'description' => $this->faker->paragraph,
            'status' => 'approved',
        ];
    }
}
