<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Staudenmeir\BelongsToThrough\BelongsToThrough;

class Product extends Model
{
    use HasFactory;
    // use SoftDeletes;
    protected $fillable = [
        'name',
        'description',
        'category_id',
        'price',
        'seller_id',

    ];
    protected $casts = ['name' => 'array', 'description' => 'array'];
    protected $appends = ['isNew'];
    protected $dates = ['created_at'];
    public function getIsNewAttribute()
    {
        if (!$this->created_at) return false;

        return $this->created_at->gt(now()->subDays(2));
    }
    public function getNameAttribute($value)
    {
        $locale = app()->getLocale();
        return $this->attributes['name'] = $this->castAttribute('name', $value)[$locale] ?? null;
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
    public function user()
    {
        return $this->belongsToThrough(User::class, Seller::class);
    }
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}
