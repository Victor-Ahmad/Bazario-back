<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UpgradeAccountRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_request_seller_upgrade(): void
    {
        $role = Role::create(['name' => 'customer']);
        $user = User::factory()->create([
            'email' => 'customer@example.com',
            'phone' => '+123456789',
        ]);
        $user->assignRole($role);

        Sanctum::actingAs($user);

        $this->postJson('/api/customer/upgrade-to-seller', [
            'store_owner_name' => 'Owner',
            'store_name' => 'My Store',
            'address' => 'Somewhere',
        ], ['Accept-Language' => 'en'])->assertStatus(200);
    }

    public function test_non_customer_cannot_request_upgrade(): void
    {
        $user = User::factory()->create([
            'email' => 'noncustomer@example.com',
            'phone' => '+123456789',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/customer/upgrade-to-seller', [
            'store_owner_name' => 'Owner',
            'store_name' => 'My Store',
            'address' => 'Somewhere',
        ], ['Accept-Language' => 'en'])->assertStatus(403);
    }
}
