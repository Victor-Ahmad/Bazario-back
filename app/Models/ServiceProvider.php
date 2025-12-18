<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceProvider extends Model
{
    use HasFactory;
    protected $table = 'service_providers';
    protected $fillable = [
        'user_id',
        'name',
        'address',
        'logo',
        'description',
        'status'

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
