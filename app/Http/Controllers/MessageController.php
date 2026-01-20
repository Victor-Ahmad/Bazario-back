<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function index(Request $r, Conversation $conversation)
    {
        $this->authorizeParticipant($r->user()->id, $conversation->id);

        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->orderBy('created_at')
            ->paginate(30);

        return response()->json(['success' => 1, 'result' => $messages]);
    }

    public function store(Request $r, Conversation $conversation)
    {
        $this->authorizeParticipant($r->user()->id, $conversation->id);

        $data = $r->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $this->chat->sendMessage($conversation, $r->user()->id, $data['body']);

        broadcast(new MessageSent($message))->toOthers();

        $this->chat->broadcastUnreadCount($message->recipient_id);

        return response()->json([
            'success' => 1,
            'message' => $message->load('sender:id,name')
        ]);
    }

    public function ackDelivered(Request $r, Message $message)
    {
        $this->authorizeParticipant($r->user()->id, $message->conversation_id);

        abort_unless(
            $r->user()->id === $message->recipient_id,
            403,
            __('chat.only_recipient_ack')
        );

        $updated = $this->chat->markDelivered($message);

        return [
            'success' => 1,
            'delivered_at' => $message->delivered_at?->toISOString(),
            'changed' => (bool) $updated,
        ];
    }

    public function markRead(Request $r, Message $message)
    {
        $this->authorizeParticipant($r->user()->id, $message->conversation_id);

        abort_unless(
            $r->user()->id === $message->recipient_id,
            403,
            __('chat.only_recipient_read')
        );

        $updated = $this->chat->markRead($message);

        if ($updated) {
            $this->chat->broadcastUnreadCount($r->user()->id);
        }

        return [
            'success' => 1,
            'read_at' => $message->read_at?->toISOString(),
            'changed' => (bool) $updated,
        ];
    }

    public function markConversationRead(Request $r, Conversation $conversation)
    {
        $this->authorizeParticipant($r->user()->id, $conversation->id);

        $result = $this->chat->markConversationRead($conversation, $r->user()->id);

        $this->chat->broadcastUnreadCount($r->user()->id);

        return [
            'success' => 1,
            'updated' => $result['updated'],
            'read_at' => $result['read_at']->toISOString(),
        ];
    }

    private function authorizeParticipant(int $userId, int $conversationId): void
    {
        $isParticipant = Conversation::where('id', $conversationId)
            ->whereHas('participants', fn($q) => $q->where('user_id', $userId))
            ->exists();

        abort_unless($isParticipant, 403);
    }
}
