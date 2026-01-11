<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceProviderWorkingHour extends Model
{
    protected $fillable = ['service_provider_id', 'day_of_week', 'start_time', 'end_time'];

    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }
}
