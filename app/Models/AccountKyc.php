<?php

namespace App\Models;

use App\Models\Concerns\HasPrismaId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Prisma model: accountkyc (table "accountkyc").
 */
class AccountKyc extends Model
{
    use HasPrismaId;

    protected $table = 'accountkyc';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * accountkyc bank column => [display label, name column].
     * Mirrors nimcweb's GetReservedAccounts.
     */
    private const BANK_COLUMNS = [
        'palmpay' => ['PalmPay Bank', 'name'],
        'palmpay2' => ['PalmPay Business', 'palmpay2_name'],
        'moniepoint' => ['Moniepoint Bank', 'name'],
        'wema' => ['Wema Bank', 'wema_name'],
        'providus' => ['Providus Bank', 'name'],
        'sterling' => ['Sterling Bank', 'name'],
        'opay' => ['Opay Bank', 'name'],
        'fidelity' => ['Fidelity Bank', 'name'],
        'Ninesp' => ['9PSB Bank', 'ninesp_name'],
    ];

    /**
     * Flatten the per-bank columns into a list of usable accounts
     * (skips empty / "0" columns).
     *
     * @return array<int, array{bank: string, account_number: string, account_name: string}>
     */
    public function toFormattedAccounts(): array
    {
        $accounts = [];

        foreach (self::BANK_COLUMNS as $column => [$label, $nameColumn]) {
            $number = $this->{$column} ?? null;

            if ($number && $number !== '0') {
                $accounts[] = [
                    'bank' => $label,
                    'account_number' => $number,
                    'account_name' => $this->{$nameColumn} ?: ($this->name ?: 'Account'),
                ];
            }
        }

        return $accounts;
    }
}
