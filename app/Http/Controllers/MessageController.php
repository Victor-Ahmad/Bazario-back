<?php

namespace App\Http\Controllers;

use App\Events\MessageDelivered;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Events\ConversationsUnreadUpdated;

class MessageController extends Controller
{
    public function index(Request $r, Conversation $conversation)
    {
        $this->authorizeParticipant($r->user()->id, $conversation->id);

        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->orderBy('created_at', 'asc')
            ->paginate(30);

        return response()->json(['success' => 1, 'result' => $messages]);
    }

    public function store(Request $r, Conversation $conversation)
    {
        $this->authorizeParticipant($r->user()->id, $conversation->id);

        $data = $r->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        // Determine the other participant (since it's 1-1)
        $participantIds = $conversation->participants()->pluck('users.id')->all();
        if (!in_array($r->user()->id, $participantIds)) abort(403);
        $recipientId = (int) array_values(array_diff($participantIds, [$r->user()->id]))[0];

        $message = $conversation->messages()->create([
            'sender_id'    => $r->user()->id,
            'recipient_id' => $recipientId,
            'body'         => $data['body'],
        ]);

        // Broadcast "sent" (appears instantly for both sides; status = 'sent')
        broadcast(new MessageSent($message))->toOthers();

        $total = Message::where('recipient_id', $recipientId)
            ->whereNull('read_at')
            ->distinct('conversation_id')
            ->count('conversation_id');

        event(new ConversationsUnreadUpdated($recipientId, $total));

        return response()->json([
            'success' => 1,
            'message' => $message->load('sender:id,name')
        ]);
    }

    // Client-side ACK (recipient confirms device received message via WS)
    public function ackDelivered(Request $r, Message $message)
    {
        $this->authorizeParticipant($r->user()->id, $message->conversation_id);

        if ($r->user()->id !== $message->recipient_id) {
            abort(403, "Only the recipient can ack delivery.");
        }

        if (is_null($message->delivered_at)) {
            $message->update(['delivered_at' => now()]);
            broadcast(new MessageDelivered($message))->toOthers();
        }

        return ['success' => 1, 'delivered_at' => $message->delivered_at?->toISOString()];
    }

    // Recipient viewed the message
    public function markRead(Request $r, Message $message)
    {
        $this->authorizeParticipant($r->user()->id, $message->conversation_id);

        if ($r->user()->id !== $message->recipient_id) {
            abort(403, "Only the recipient can mark read.");
        }

        if (is_null($message->read_at)) {
            DB::transaction(function () use ($message) {
                $message->update(['read_at' => now()]);
                // also bump conversation pivot last_read_at
                $message->conversation->participants()
                    ->updateExistingPivot($message->recipient_id, ['last_read_at' => now()]);
            });
            broadcast(new MessageRead($message))->toOthers();

            $total = Message::where('recipient_id', $r->user()->id)
                ->whereNull('read_at')
                ->distinct('conversation_id')
                ->count('conversation_id');

            event(new ConversationsUnreadUpdated($r->user()->id, $total));
        }

        return ['success' => 1, 'read_at' => $message->read_at?->toISOString()];
    }

    // Optional: mark all messages addressed to me as read in this conversation
    public function markConversationRead(Request $r, Conversation $conversation)
    {
        $this->authorizeParticipant($r->user()->id, $conversation->id);

        $now = now();
        $updated = $conversation->messages()
            ->where('recipient_id', $r->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => $now]);

        $conversation->participants()->updateExistingPivot($r->user()->id, ['last_read_at' => $now]);


        $total = Message::where('recipient_id', $r->user()->id)
            ->whereNull('read_at')
            ->distinct('conversation_id')
            ->count('conversation_id');

        event(new ConversationsUnreadUpdated($r->user()->id, $total));

        // You could also broadcast a bulk-read event if you want
        return ['success' => 1, 'updated' => $updated, 'read_at' => $now->toISOString()];
    }

    private function authorizeParticipant(int $userId, int $conversationId): void
    {
        $isParticipant = \App\Models\Conversation::where('id', $conversationId)
            ->whereHas('participants', fn($q) => $q->where('user_id', $userId))
            ->exists();

        abort_unless($isParticipant, 403);
    }
}
