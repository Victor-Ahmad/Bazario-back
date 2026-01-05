<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['type', 'direct_key'];

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps()
            ->withPivot('last_read_at');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    public static function directKey(int $a, int $b): string
    {
        [$min, $max] = [min($a, $b), max($a, $b)];
        return "{$min}:{$max}";
    }


    // Query helpers 
    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->whereHas('participants', fn($p) => $p->where('users.id', $userId));
    }

    public function scopeWithChatListData(Builder $q, int $userId): Builder
    {
        return $q
            ->withMax('messages as last_message_at', 'created_at')
            ->withCount([
                'messages as unread_messages_count' => fn(Builder $b) =>
                $b->whereNull('read_at')->where('recipient_id', $userId)
            ])
            ->with([
                'participants:id,name,email',
                'latestMessage' => fn($m) => $m->select(
                    'messages.id',
                    'messages.conversation_id',
                    'messages.sender_id',
                    'messages.recipient_id',
                    'messages.body',
                    'messages.created_at',
                    'messages.delivered_at',
                    'messages.read_at'
                ),
                'latestMessage.sender:id,name',
            ]);
    }

    public function scopeSearch(Builder $q, string $term, int $userId): Builder
    {
        $term = trim($term);
        if ($term === '') return $q;

        return $q->where(function (Builder $w) use ($term, $userId) {
            $w->whereHas('participants', function (Builder $p) use ($term, $userId) {
                $p->where('users.id', '!=', $userId)
                    ->where(
                        fn(Builder $pp) =>
                        $pp->where('users.name', 'like', "%{$term}%")
                            ->orWhere('users.email', 'like', "%{$term}%")
                    );
            })->orWhereHas(
                'messages',
                fn(Builder $m) =>
                $m->where('body', 'like', "%{$term}%")
            );
        });
    }


    // response shap
    public function toChatListItem(int $userId): array
    {
        $peer = $this->participants->firstWhere('id', '!=', $userId);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'last_message_at' => $this->last_message_at
                ? Carbon::parse($this->last_message_at)->toISOString()
                : null,
            'unread_count' => (int) ($this->unread_messages_count ?? 0),

            'peer' => $peer ? [
                'id' => $peer->id,
                'name' => $peer->name,
                'email' => $peer->email ?? null,
            ] : null,

            'latest_message' => $this->latestMessage ? [
                'id' => $this->latestMessage->id,
                'body' => $this->latestMessage->body,
                'created_at' => $this->latestMessage->created_at->toISOString(),
                'sender' => [
                    'id' => $this->latestMessage->sender_id,
                    'name' => optional($this->latestMessage->sender)->name,
                ],
                'delivered_at' => optional($this->latestMessage->delivered_at)?->toISOString(),
                'read_at' => optional($this->latestMessage->read_at)?->toISOString(),
            ] : null,
        ];
    }
}
