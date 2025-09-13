<?php

namespace App\Models;

use App\Events\SettingsUpdated;
use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'is_public',
        'version',
        'created_at',
        'updated_at',
        'updated_by',
    ];

    protected $casts = [
        'value' => 'array',
        'is_public' => 'boolean',
        'version' => 'integer',
        'updated_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::saved(function ($setting) {
            event(new SettingsUpdated($setting));
        });

        static::deleted(function ($setting) {
            event(new SettingsUpdated($setting));
        });
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function get($key, $default = null)
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : $default;
    }

    public static function set($key, $value, $type = 'object', $isPublic = false, $description = null)
    {
        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => $type,
                'is_public' => $isPublic,
                'description' => $description,
                'updated_by' => auth()->id(),
                'version' => static::where('key', $key)->value('version') + 1 ?? 1,
            ]
        );
    }

    public static function getPublicSettings()
    {
        return static::where('is_public', true)
            ->pluck('value', 'key')
            ->toArray();
    }

    public static function getAllSettings()
    {
        return static::all()
            ->pluck('value', 'key')
            ->toArray();
    }
}
