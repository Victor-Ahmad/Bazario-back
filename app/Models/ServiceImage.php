<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class ServiceImage extends Model
{
    protected $fillable = ['service_id', 'image'];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
