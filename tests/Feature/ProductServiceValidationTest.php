<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductServiceValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_create_requires_seller_and_fields(): void
    {
        Role::create(['name' => 'seller']);
        $user = User::factory()->create();
        $user->assignRole('seller');
        Sanctum::actingAs($user);

        $this->postJson('/api/product', [
            'name' => ['en' => 'Test', 'ar' => 'اختبار'],
            'price' => 10,
        ], ['Accept-Language' => 'en'])->assertStatus(422);

        $category = Category::factory()->create();
        $seller = Seller::factory()->create(['user_id' => $user->id]);

        $this->postJson('/api/product', [
            'name' => ['en' => 'Test', 'ar' => 'اختبار'],
            'description' => ['en' => 'Desc', 'ar' => 'وصف'],
            'price' => 10,
            'category_id' => $category->id,
        ], ['Accept-Language' => 'en'])->assertStatus(200);

        $this->assertTrue(
            Product::where('seller_id', $seller->id)->exists()
        );
    }

    public function test_product_create_fails_without_seller_profile(): void
    {
        Role::create(['name' => 'seller']);
        $user = User::factory()->create();
        $user->assignRole('seller');
        Sanctum::actingAs($user);

        $category = Category::factory()->create();

        $this->postJson('/api/product', [
            'name' => ['en' => 'Test', 'ar' => 'اختبار'],
            'price' => 10,
            'category_id' => $category->id,
        ], ['Accept-Language' => 'en'])->assertStatus(404);
    }

    public function test_service_create_requires_provider_and_fields(): void
    {
        Role::create(['name' => 'service_provider']);
        $user = User::factory()->create();
        $user->assignRole('service_provider');
        Sanctum::actingAs($user);

        $this->postJson('/api/service', [
            'title' => ['en' => 'Service', 'ar' => 'خدمة'],
            'price' => 20,
        ], ['Accept-Language' => 'en'])->assertStatus(422);

        $category = Category::factory()->create();
        $provider = ServiceProvider::factory()->create(['user_id' => $user->id]);

        $this->postJson('/api/service', [
            'title' => ['en' => 'Service', 'ar' => 'خدمة'],
            'description' => ['en' => 'Desc', 'ar' => 'وصف'],
            'price' => 20,
            'category_id' => $category->id,
        ], ['Accept-Language' => 'en'])->assertStatus(200);

        $this->assertTrue(
            Service::where('provider_id', $provider->id)->exists()
        );
    }

    public function test_service_create_fails_without_provider_profile(): void
    {
        Role::create(['name' => 'service_provider']);
        $user = User::factory()->create();
        $user->assignRole('service_provider');
        Sanctum::actingAs($user);

        $category = Category::factory()->create();

        $this->postJson('/api/service', [
            'title' => ['en' => 'Service', 'ar' => 'خدمة'],
            'price' => 20,
            'category_id' => $category->id,
        ], ['Accept-Language' => 'en'])->assertStatus(404);
    }
}
