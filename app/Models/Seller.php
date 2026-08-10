<?php

namespace App\Models;

use App\Support\MediaPath;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    protected $appends = [
        'logo_url',
    ];

    protected $fillable = [
        'user_id',
        'store_owner_name',
        'store_name',
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

    public function getLogoUrlAttribute(): ?string
    {
        return MediaPath::publicUrl($this->logo);
    }
}
