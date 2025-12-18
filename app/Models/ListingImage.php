<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingImage extends Model
{
    protected $fillable = [
        'listing_id',
        'path',
        'sort',
        'is_cover',
    ];

    protected $casts = [
        'is_cover' => 'boolean',
        'sort'     => 'integer',
    ];

    public function listing()
    {
        return $this->belongsTo(Listing::class);
    }
}
