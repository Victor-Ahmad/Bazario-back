<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    // use SoftDeletes;
    protected $fillable = [
        'name',
        'description',
        'category_id',
        'price',
        'seller_id',

    ];
    protected $casts = ['name' => 'array', 'description' => 'array'];

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

    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
}
