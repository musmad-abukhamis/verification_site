<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkPrefix extends Model
{
    protected $fillable = ['network', 'prefix'];

    /**
     * The admin-editable prefix map: ['MTN' => ['0703', ...], ...].
     *
     * @return array<string, array<int, string>>
     */
    public static function map(): array
    {
        return static::query()
            ->orderBy('prefix')
            ->get()
            ->groupBy('network')
            ->map(fn ($rows) => $rows->pluck('prefix')->all())
            ->all();
    }
}
