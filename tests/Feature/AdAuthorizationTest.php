<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\AdPosition;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_create_ad_for_other_users_product(): void
    {
        $owner = User::factory()->create();
        $seller = Seller::factory()->create(['user_id' => $owner->id]);
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->postJson(
            '/api/ads',
            [
                'title' => 'Test Ad',
                'adable_type' => 'product',
                'adable_id' => $product->id,
            ],
            ['Accept-Language' => 'en']
        )->assertStatus(403);
    }

    public function test_owner_can_create_ad_for_own_product(): void
    {
        $owner = User::factory()->create();
        $seller = Seller::factory()->create(['user_id' => $owner->id]);
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $position = AdPosition::create([
            'name' => 'test_position',
            'label' => 'Test Position',
            'priority' => 0,
        ]);

        Sanctum::actingAs($owner);

        $response = $this->postJson(
            '/api/ads',
            [
                'title' => 'Owner Ad',
                'adable_type' => 'product',
                'adable_id' => $product->id,
                'ad_position_id' => $position->id,
            ],
            ['Accept-Language' => 'en']
        );
        $response->assertStatus(200);
    }
}
