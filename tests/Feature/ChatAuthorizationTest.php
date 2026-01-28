<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_participant_cannot_view_messages(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $intruder = User::factory()->create();

        $conversation = Conversation::create([
            'type' => 'direct',
            'direct_key' => Conversation::directKey($userA->id, $userB->id),
        ]);
        $conversation->participants()->attach([$userA->id, $userB->id]);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/conversations/{$conversation->id}/messages", [
            'Accept-Language' => 'en',
        ])->assertStatus(403);
    }

    public function test_participant_can_send_message(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'type' => 'direct',
            'direct_key' => Conversation::directKey($userA->id, $userB->id),
        ]);
        $conversation->participants()->attach([$userA->id, $userB->id]);

        config(['broadcasting.default' => 'null']);
        Sanctum::actingAs($userA);

        $this->postJson("/api/conversations/{$conversation->id}/messages", [
            'body' => 'Hello',
        ], ['Accept-Language' => 'en'])->assertStatus(200);

        $this->assertTrue(
            Message::where('conversation_id', $conversation->id)->exists()
        );
    }
}
