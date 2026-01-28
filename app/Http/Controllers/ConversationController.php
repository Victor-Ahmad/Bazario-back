<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(private readonly ChatService $chat) {}

    public function startDirect(Request $r)
    {
        $data = $r->validate([
            'user_id' => ['required', 'integer', 'exists:users,id']
        ]);

        $authId = $r->user()->id;
        $otherId = (int) $data['user_id'];

        abort_if($authId === $otherId, 422, __('chat.cannot_chat_self'));

        $conversation = Conversation::firstOrCreate(
            ['direct_key' => Conversation::directKey($authId, $otherId)],
            ['type' => 'direct']
        );

        $conversation->participants()->syncWithoutDetaching([$authId, $otherId]);

        return response()->json([
            'success' => 1,
            'conversation' => $conversation->only('id', 'type', 'direct_key')
        ]);
    }

    public function unreadCount(Request $r)
    {
        $userId = $r->user()->id;

        return response()->json([
            'success' => 1,
            'result' => ['total' => $this->chat->unreadChatsCount($userId)],
        ]);
    }

    public function index(Request $r)
    {
        $userId  = $r->user()->id;
        $perPage = min(50, max(1, (int) $r->query('per_page', 20)));

        $conversations = Conversation::query()
            ->forUser($userId)
            ->withChatListData($userId)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $conversations->setCollection(
            $conversations->getCollection()->map(fn(Conversation $c) => $c->toChatListItem($userId))
        );

        return response()->json(['success' => 1, 'result' => $conversations]);
    }
}
