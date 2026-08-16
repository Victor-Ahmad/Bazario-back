<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'price',
        'duration_days',
        'expires_at',
        'status',
        'paid_at',
        'refund_status',
        'metadata',
        'adable_type',
        'adable_id',
        'ad_position_id',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'currency_iso',
        'refund',
    ];

    public function adable()
    {
        return $this->morphTo();
    }

    public function images()
    {
        return $this->hasMany(AdImage::class);
    }

    public function position()
    {
        return $this->belongsTo(AdPosition::class, 'ad_position_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCurrencyIsoAttribute(): string
    {
        return strtoupper((string) config('ads.currency', config('stripe.currency', 'eur')));
    }

    public function getRefundAttribute(): array
    {
        $refund = $this->metadata['refund'] ?? null;

        return [
            'applied' => $this->refund_status === 'refunded' || (bool) ($refund['applied'] ?? false),
            'status' => $refund['status'] ?? $this->refund_status,
            'amount' => $refund['amount'] ?? null,
            'stripe_refund_id' => $refund['stripe_refund_id'] ?? null,
        ];
    }
}
