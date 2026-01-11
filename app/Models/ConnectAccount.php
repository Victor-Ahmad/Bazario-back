<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConnectAccount extends Model
{
    protected $fillable = [
        'user_id',
        'stripe_account_id',
        'type',
        'charges_enabled',
        'payouts_enabled',
        'details_submitted',
        'onboarding_completed_at',
        'requirements',
    ];

    protected $casts = [
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'details_submitted' => 'boolean',
        'onboarding_completed_at' => 'datetime',
        'requirements' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
