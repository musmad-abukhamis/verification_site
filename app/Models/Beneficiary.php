<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Beneficiary extends Model
{
    use HasPrismaId;

    protected $fillable = ['user_id', 'phone', 'network', 'is_ported', 'label'];

    protected function casts(): array
    {
        return ['is_ported' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
