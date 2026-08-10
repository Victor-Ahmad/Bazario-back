<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceProvider extends Model
{
    use HasFactory;

    protected $appends = [
        'logo_url',
    ];

    protected $table = 'service_providers';
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'logo',
        'description',
        'status'

    ];

    public function scopeWithActiveUser($query)
    {
        return $query->whereHas('user');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'provider_id');
    }

    public function workingHours()
    {
        return $this->hasMany(ServiceProviderWorkingHour::class);
    }

    public function timeOffs()
    {
        return $this->hasMany(ServiceProviderTimeOff::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return MediaPath::publicUrl($this->logo);
    }
}
