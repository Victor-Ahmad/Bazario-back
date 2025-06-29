<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_owner_name',
        'store_name',
        'address',
        'logo',
        'description',
        'status'

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}