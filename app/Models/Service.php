<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory;
    // use SoftDeletes;
    protected $fillable = [
        'title',
        'description',
        'category_id',
        'price',
        'duration_minutes',
        'location_type',
        'provider_id',
        'is_active',

    ];
    protected $appends = ['isNew'];
    protected $dates = ['created_at'];
    protected $casts = ['title' => 'array', 'description' => 'array'];

    public function getIsNewAttribute()
    {
        if (!$this->created_at) return false;

        return $this->created_at->gt(now()->subDays(2));
    }

    public function getTitleAttribute($value)
    {
        $locale = app()->getLocale();
        return $this->attributes['title'] = $this->castAttribute('title', $value)[$locale] ?? null;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function talent()
    {
        return $this->belongsTo(Talent::class, 'provider_id');
    }

    public function images()
    {
        return $this->hasMany(ServiceImage::class);
    }
}
