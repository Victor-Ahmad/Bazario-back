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
    ];

    protected $casts = [
        'attributes'   => 'array',
    ];

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
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
}
