<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;
    use HasFactory;
    protected $fillable = [
        'name',
        'image',
        'parent_id',
        'slug',
        'type',
        'description',
    ];

    protected $casts = [
        'name' => 'array',
    ];

    /**
     * Accessor for getting the name in the current locale.
     */
    public function getNameAttribute($value)
    {
        $name = $this->attributes['name'] ?? null;
        $locale = app()->getLocale();

        if (is_string($name)) {
            $name = json_decode($name, true);
        }

        return $name[$locale] ?? null;
    }

    /**
     * Parent category relationship
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Children categories relationship
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
}
