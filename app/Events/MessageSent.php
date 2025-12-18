<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MessageSent implements ShouldBroadcastNow
{
    public function __construct(public Message $message)
    {
        $this->message->loadMissing('sender:id,name');
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.' . $this->message->conversation_id);
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'meta' => $this->message->meta,
            'sender' => ['id' => $this->message->sender_id, 'name' => $this->message->sender->name],
            'recipient_id' => $this->message->recipient_id,
            'delivered_at' => optional($this->message->delivered_at)?->toISOString(),
            'read_at' => optional($this->message->read_at)?->toISOString(),
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }
}
