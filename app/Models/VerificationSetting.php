<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value settings for the verification provider engine (failover toggle,
 * attempt cap, log retention). Deliberately separate from `data_settings` so
 * the two modules cannot clobber each other's keys.
 */
class VerificationSetting extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'key';

    protected $fillable = ['key', 'value'];

    protected const CACHE_KEY = 'verification_settings:all';

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

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value === null ? $default : (int) $value;
    }

    /**
     * Money-valued settings (the failed-verification charge amounts) are stored
     * as strings like "50.00", so they need their own accessor — int() would
     * silently truncate the kobo.
     */
    public static function float(string $key, float $default = 0.0): float
    {
        $value = self::get($key);

        return $value === null || $value === '' ? $default : (float) $value;
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
