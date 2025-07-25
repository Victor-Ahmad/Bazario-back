<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $fillable = [
        'file',
        'name',
        'type',
        'attachable_id',
        'attachable_type'
    ];

    public function attachable()
    {
        return $this->morphTo();
    }
}
