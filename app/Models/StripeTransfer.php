<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeTransfer extends Model
{
    protected $fillable = [
        'order_id',
        'payee_user_id',
        'transfer_id',
        'amount',
        'currency_iso',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payee()
    {
        return $this->belongsTo(User::class, 'payee_user_id');
    }
}
