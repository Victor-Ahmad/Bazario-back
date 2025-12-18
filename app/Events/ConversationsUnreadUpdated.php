<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ConversationsUnreadUpdated implements ShouldBroadcastNow
{
    public function __construct(public int $userId, public int $total) {}

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs()
    {
        return 'conversations.unread';
    }

    public function broadcastWith()
    {
        return ['total' => $this->total];
    }
}
