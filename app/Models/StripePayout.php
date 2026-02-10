<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripePayout extends Model
{
    protected $fillable = [
        'payee_user_id',
        'stripe_account_id',
        'payout_id',
        'amount',
        'currency_iso',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function payee()
    {
        return $this->belongsTo(User::class, 'payee_user_id');
    }
}
