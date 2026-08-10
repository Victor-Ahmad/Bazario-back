<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class ServiceImage extends Model
{
    use HasFactory;

    protected $appends = [
        'image_url',
    ];

    protected $fillable = ['service_id', 'image'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return MediaPath::publicUrl($this->image);
    }
}
