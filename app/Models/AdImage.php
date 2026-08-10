<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Model;

class AdImage extends Model
{
    protected $fillable = [
        'ad_id',
        'image_url',
        'sort_order',
    ];

    public function ad()
    {
        return $this->belongsTo(Ad::class);
    }

    public function getImageUrlAttribute(?string $value): ?string
    {
        return MediaPath::publicUrl($value);
    }
}
