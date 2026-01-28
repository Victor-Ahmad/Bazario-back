<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MessageEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_body_is_encrypted_at_rest(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $conversation = Conversation::create([
            'type' => 'direct',
            'direct_key' => Conversation::directKey($userA->id, $userB->id),
        ]);
        $conversation->participants()->attach([$userA->id, $userB->id]);

        $plaintext = 'Secret message';
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $userA->id,
            'recipient_id' => $userB->id,
            'body' => $plaintext,
        ]);

        $raw = DB::table('messages')->where('id', $message->id)->value('body');

        $this->assertNotSame($plaintext, $raw);
        $this->assertSame($plaintext, $message->fresh()->body);
    }
}
