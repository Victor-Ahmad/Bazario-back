<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPosition extends Model
{
    protected $fillable = ['name', 'label', 'priority'];

    public function ads()
    {
        return $this->hasMany(Ad::class);
    }
}
