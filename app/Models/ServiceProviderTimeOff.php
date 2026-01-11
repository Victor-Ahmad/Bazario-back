<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProviderTimeOff extends Model
{
    protected $fillable = ['service_provider_id', 'starts_at', 'ends_at', 'is_holiday', 'reason'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_holiday' => 'boolean',
    ];

    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }
}
