<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Simple key-value settings model.
 *
 * Stores user-editable settings that override config defaults.
 *
 * @property string $key
 * @property mixed $value
 */
class OfficeGuySetting extends Model
{
    protected $table = 'officeguy_settings';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    public $timestamps = true;

    /**
     * Get a setting value by key.
     *
     * @param string $key
     * @return mixed|null
     */
    public static function get(string $key): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting?->value;
    }

    /**
     * Set a setting value by key.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Check if a setting exists.
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return static::where('key', $key)->exists();
    }

    /**
     * Delete a setting by key.
     *
     * @param string $key
     * @return void
     */
    public static function remove(string $key): void
    {
        static::where('key', $key)->delete();
    }

    /**
     * Get all settings as an associative array.
     *
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        return static::query()
            ->pluck('value', 'key')
            ->toArray();
    }
}
