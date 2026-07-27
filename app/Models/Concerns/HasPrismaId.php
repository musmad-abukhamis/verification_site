<?php

namespace App\Models\Concerns;

/**
 * Gives an Eloquent model a string primary key that is auto-populated with a
 * cuid-style identifier on create — mirroring Prisma's `@id @default(cuid())`.
 *
 * Models that assign their own ids (Prisma fields without a default) can use
 * HasStringId instead, or simply set the key before saving.
 */
trait HasPrismaId
{
    public static function bootHasPrismaId(): void
    {
        static::creating(function ($model) {
            $key = $model->getKeyName();

            if (empty($model->{$key})) {
                $model->{$key} = self::generateCuid();
            }
        });
    }

    public function initializeHasPrismaId(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    /**
     * Generate a collision-resistant, cuid-shaped identifier
     * (lowercase, starts with "c", url-safe) compatible with data
     * originating from the Prisma `cuid()` generator.
     */
    public static function generateCuid(): string
    {
        static $counter = 0;

        $timestamp = self::toBase36((int) (microtime(true) * 1000));
        $count = str_pad(self::toBase36(($counter++) & 0xFFFF), 4, '0', STR_PAD_LEFT);
        $fingerprint = substr(self::toBase36(getmypid() ?: random_int(0, 0xFFFF)).'0000', 0, 4);
        $random = substr(bin2hex(random_bytes(6)), 0, 8);

        return 'c'.$timestamp.$count.$fingerprint.$random;
    }

    private static function toBase36(int $number): string
    {
        return $number === 0 ? '0' : base_convert((string) $number, 10, 36);
    }
}
