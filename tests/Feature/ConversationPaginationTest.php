<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_page_is_clamped(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'type' => 'direct',
            'direct_key' => Conversation::directKey($userA->id, $userB->id),
        ]);
        $conversation->participants()->attach([$userA->id, $userB->id]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/conversations?per_page=200', [
            'Accept-Language' => 'en',
        ]);

        $response->assertStatus(200);
        $perPage = $response->json('result.per_page');
        $this->assertNotNull($perPage);
        $this->assertLessThanOrEqual(50, $perPage);
    }
}
