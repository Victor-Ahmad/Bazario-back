<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryTypeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_rejects_invalid_category_type(): void
    {
        $this->getJson('/api/categories?type=invalid', [
            'Accept-Language' => 'en',
        ])->assertStatus(422);
    }

    public function test_store_rejects_invalid_category_type(): void
    {
        Role::create(['name' => 'seller']);
        $user = User::factory()->create();
        $user->assignRole('seller');
        Sanctum::actingAs($user);

        $this->postJson('/api/category', [
            'name' => ['en' => 'Cat', 'ar' => 'تصنيف'],
            'type' => 'invalid',
        ], ['Accept-Language' => 'en'])->assertStatus(422);
    }
}
