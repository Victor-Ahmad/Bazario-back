<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'image'];
    protected $casts = ['name' => 'array'];

    public function getNameAttribute($value)
    {
        $locale = app()->getLocale();
        return $this->attributes['name'] = $this->castAttribute('name', $value)[$locale] ?? null;
    }
}