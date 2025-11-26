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
     */
    public static function get(string $key): mixed
    {
        return static::query()
            ->where('key', $key)
            ->value('value');
    }

    /**
     * Set a setting value by key.
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }

    /**
     * Check if a setting exists.
     */
    public static function has(string $key): bool
    {
        return static::query()
            ->where('key', $key)
            ->exists();
    }

    /**
     * Delete a setting by key.
     */
    public static function remove(string $key): void
    {
        static::query()
            ->where('key', $key)
            ->delete();
    }

    /**
     * Get all settings as associative array.
     *
     * IMPORTANT:
     * Must NOT override Model::all().
     */
    public static function allAsArray(): array
    {
        return static::query()
            ->pluck('value', 'key')
            ->toArray();
    }
}