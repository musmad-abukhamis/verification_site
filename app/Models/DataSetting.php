<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Simple key/value store for the data module (failover toggle, retry/cutoff
 * intervals). Named data_settings to avoid the admin pricing `settings` table.
 */
class DataSetting extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'key';

    protected $fillable = ['key', 'value'];

    protected const CACHE_KEY = 'data_settings:all';

    /**
     * @return array<string, string|null>
     */
    public static function all(...$args): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            return static::query()->pluck('value', 'key')->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null ? $default : (int) $value;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
        );

        Cache::forget(self::CACHE_KEY);
    }

    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
