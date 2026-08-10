<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Listing extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'price',
        'attributes',
        'status',
        'paid_at',
        'refund_status',
        'metadata',
    ];

    protected $casts = [
        'attributes'   => 'array',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $appends = [
        'currency_iso',
        'refund',
    ];

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort')->orderBy('id');
    }

    public function coverImage()
    {
        return $this->hasOne(ListingImage::class)->where('is_cover', true);
    }
    public function ads()
    {
        return $this->morphMany(Ad::class, 'adable');
    }

    public function getCurrencyIsoAttribute(): string
    {
        return strtoupper((string) config('listings.currency', config('stripe.currency', 'eur')));
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
