<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'price',
        'expires_at',
        'status',
        'paid_at',
        'metadata',
        'adable_type',
        'adable_id',
        'ad_position_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
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
}
