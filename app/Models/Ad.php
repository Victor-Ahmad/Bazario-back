<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'title',
        'description',
        'phone',
        'email',
        'image',
        'category_id',
        'price',
        'added_by',
        'quantity'

    ];

    protected $casts = [
        'title' => 'array',
        'description' => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function seller()
    {
        return $this->belongsTo(Seller::class, 'added_by');
    }

    public function images()
    {
        return $this->hasMany(AdImage::class);
    }
}
