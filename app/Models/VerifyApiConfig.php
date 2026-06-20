<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Prisma model: verifyapiconfiq (single-row config, string id defaulting "API1").
 */
class VerifyApiConfig extends Model
{
    protected $table = 'verifyapiconfiq';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $attributes = [
        'id' => 'API1',
    ];

    protected $guarded = [];
}
