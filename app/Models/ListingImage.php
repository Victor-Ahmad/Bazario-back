<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Model;

class ListingImage extends Model
{
    protected $appends = [
        'image_url',
    ];

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

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        return MediaPath::publicUrl($this->path);
    }
}
