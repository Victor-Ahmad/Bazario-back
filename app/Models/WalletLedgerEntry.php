<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletLedgerEntry extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'order_item_id',
        'type',
        'amount',
        'currency_iso',
        'available_on',
        'metadata',
    ];

    protected $casts = [
        'available_on' => 'datetime',
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }
}
