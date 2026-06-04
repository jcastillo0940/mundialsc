<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getOrConfig(string $key, string $configKey, ?string $default = null): ?string
    {
        $value = static::get($key);

        if ($value !== null && $value !== '') {
            return $value;
        }

        $configValue = config($configKey);

        if (is_string($configValue) && $configValue !== '') {
            return $configValue;
        }

        return $default;
    }
}
