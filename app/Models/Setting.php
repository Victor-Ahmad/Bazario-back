<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, $default = null)
    {
        $row = static::query()->where('key', $key)->first();
        if (!$row) {
            return $default;
        }

        $value = $row->value;
        if ($value === null) {
            return $default;
        }

        if (is_numeric($value)) {
            return $value + 0;
        }

        $decoded = json_decode($value, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    public static function setValue(string $key, $value): self
    {
        $stored = is_scalar($value) ? (string) $value : json_encode($value);

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $stored]
        );
    }
}
