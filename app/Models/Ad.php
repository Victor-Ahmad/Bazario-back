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
        'adable_type',
        'adable_id',
        'ad_position_id',
    ];

    protected $dates = ['expires_at'];

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
