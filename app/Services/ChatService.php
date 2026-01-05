<?php

namespace App\Services;

use App\Events\ConversationsUnreadUpdated;
use App\Events\MessageDelivered;
use App\Events\MessageRead;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

class ChatService
{
    public function unreadChatsCount(int $userId): int
    {
        return Message::query()
            ->where('recipient_id', $userId)
            ->whereNull('read_at')
            ->distinct('conversation_id')
            ->count('conversation_id');
    }

    public function broadcastUnreadCount(int $userId): void
    {
        event(new ConversationsUnreadUpdated($userId, $this->unreadChatsCount($userId)));
    }

    public function recipientIdForDirectConversation(Conversation $conversation, int $senderId): int
    {
        $ids = $conversation->participants()->pluck('users.id')->all();

        if (!in_array($senderId, $ids, true)) {
            abort(403);
        }

        $others = array_values(array_diff($ids, [$senderId]));

        if (count($others) !== 1) {
            abort(422, 'Direct conversation must have exactly 2 participants.');
        }

        return (int) $others[0];
    }

    public function sendMessage(Conversation $conversation, int $senderId, string $body): Message
    {
        $recipientId = $this->recipientIdForDirectConversation($conversation, $senderId);

        return $conversation->messages()->create([
            'sender_id'    => $senderId,
            'recipient_id' => $recipientId,
            'body'         => $body,
        ]);
    }

    public function markDelivered(Message $message): ?Message
    {
        if ($message->delivered_at) {
            return null;
        }

        $message->update(['delivered_at' => now()]);
        broadcast(new MessageDelivered($message))->toOthers();

        return $message;
    }

    public function markRead(Message $message): ?Message
    {
        if ($message->read_at) {
            return null;
        }

        DB::transaction(function () use ($message) {
            $now = now();

            $message->update(['read_at' => $now]);

            $message->conversation
                ->participants()
                ->updateExistingPivot($message->recipient_id, ['last_read_at' => $now]);
        });

        broadcast(new MessageRead($message))->toOthers();

        return $message;
    }

    public function markConversationRead(Conversation $conversation, int $userId): array
    {
        $now = now();

        $updated = $conversation->messages()
            ->where('recipient_id', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => $now]);

        $conversation->participants()->updateExistingPivot($userId, ['last_read_at' => $now]);

        return ['updated' => $updated, 'read_at' => $now];
    }
}
