<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    // Latest message (Laravel 9+: latestOfMany)
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('created_at');
    }

    // Convenience: return the other participant for a given user id
    public function peerFor(int $userId): ?User
    {
        return $this->participants->firstWhere('id', '!=', $userId);
    }

    public static function directKey(int $a, int $b): string
    {
        [$min, $max] = [min($a, $b), max($a, $b)];
        return "{$min}:{$max}";
    }
}
