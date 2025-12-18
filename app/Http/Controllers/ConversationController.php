<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;

class ConversationController extends Controller
{
    // Create or fetch the direct conversation between auth user and target user_id
    public function startDirect(Request $r)
    {
        $data = $r->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $authId = $r->user()->id;
        abort_if($authId === (int)$data['user_id'], 422, "Cannot chat with yourself.");

        $key = Conversation::directKey($authId, (int)$data['user_id']);

        $conversation = Conversation::firstOrCreate(
            ['direct_key' => $key],
            ['type' => 'direct']
        );

        // ensure both participants are attached
        $conversation->participants()->syncWithoutDetaching([$authId, (int)$data['user_id']]);

        return response()->json(['success' => 1, 'conversation' => $conversation->only('id', 'type', 'direct_key')]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user();

        $totalUnreadChats = Message::where('recipient_id', $user->id)
            ->whereNull('read_at')
            ->distinct('conversation_id')
            ->count('conversation_id');

        return response()->json([
            'success' => 1,
            'result' => ['total' => $totalUnreadChats],
        ]);
    }

    public function index(Request $r)
    {
        $user = $r->user();
        $q = trim((string) $r->query('q', ''));
        $perPage = (int) $r->query('per_page', 20);

        // Base: conversations I participate in
        $base = Conversation::query()
            ->whereHas('participants', fn($p) => $p->where('users.id', $user->id))
            // last_message_at: use withMax for sorting
            ->withMax('messages as last_message_at', 'created_at')
            // unread count (only my unread)
            ->withCount([
                'messages as unread_messages_count' => function (Builder $b) use ($user) {
                    $b->whereNull('read_at')
                        ->where('recipient_id', $user->id);
                }
            ])
            // eager load relations
            ->with([
                'participants:id,name,email',
                'latestMessage' => function ($q) {
                    $q->select(
                        'messages.id',
                        'messages.conversation_id',
                        'messages.sender_id',
                        'messages.recipient_id',
                        'messages.body',
                        'messages.created_at',
                        'messages.delivered_at',
                        'messages.read_at'
                    );
                },
                'latestMessage.sender:id,name',

            ]);

        // Optional search by peer name/email (or message text)
        if ($q !== '') {
            $base->where(function (Builder $w) use ($q, $user) {
                // search peer participant
                $w->whereHas('participants', function (Builder $p) use ($q, $user) {
                    $p->where('users.id', '!=', $user->id)
                        ->where(function (Builder $pp) use ($q) {
                            $pp->where('users.name', 'like', "%{$q}%")
                                ->orWhere('users.email', 'like', "%{$q}%");
                        });
                })
                    // or search latest message body (optional, remove if heavy)
                    ->orWhereHas('messages', function (Builder $m) use ($q) {
                        $m->where('body', 'like', "%{$q}%");
                    });
            });
        }

        $conversations = $base
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        // Transform to include a top-level "peer" object
        $data = $conversations->getCollection()->map(function (Conversation $c) use ($user) {
            $peer = $c->participants->firstWhere('id', '!=', $user->id);
            return [
                'id' => $c->id,
                'type' => $c->type,
                'last_message_at' => $c->last_message_at
                    ? Carbon::parse($c->last_message_at)->toISOString()
                    : null,

                'unread_count' => $c->unread_messages_count,
                'peer' => $peer ? [
                    'id' => $peer->id,
                    'name' => $peer->name,
                    'email' => $peer->email ?? null,

                ] : null,
                'latest_message' => $c->latestMessage ? [
                    'id' => $c->latestMessage->id,
                    'body' => $c->latestMessage->body,
                    'created_at' => $c->latestMessage->created_at->toISOString(),
                    'sender' => [
                        'id' => $c->latestMessage->sender_id,
                        'name' => optional($c->latestMessage->sender)->name,
                    ],
                    'delivered_at' => optional($c->latestMessage->delivered_at)?->toISOString(),
                    'read_at' => optional($c->latestMessage->read_at)?->toISOString(),
                ] : null,
            ];
        });

        // Put transformed collection back into paginator structure
        $conversations->setCollection(collect($data));

        return response()->json([
            'success' => 1,
            'result' => $conversations,
        ]);
    }
}
