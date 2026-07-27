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
     * Columns written by the CURRENT provider (PayVessel): PalmPay lands in
     * `palmpay2`, 9PSB in `Ninesp`.
     *
     * Everything else in BANK_COLUMNS was issued by Billstack or the earlier
     * xixapay integration and is no longer offered, so it is hidden from users.
     *
     * Display only. The webhooks still search every bank column when matching a
     * payment, so money sent to an account issued long ago continues to credit
     * the right wallet -- it just is not advertised any more.
     */
    private const CURRENT_PROVIDER_COLUMNS = ['palmpay2', 'Ninesp'];

    /**
     * Flatten the per-bank columns into a list of usable accounts
     * (skips empty / "0" columns).
     *
     * @param  bool  $includeLegacy  include accounts from retired providers
     * @return array<int, array{bank: string, account_number: string, account_name: string}>
     */
    public function toFormattedAccounts(bool $includeLegacy = false): array
    {
        $accounts = [];

        foreach (self::BANK_COLUMNS as $column => [$label, $nameColumn]) {
            if (! $includeLegacy && ! in_array($column, self::CURRENT_PROVIDER_COLUMNS, true)) {
                continue;
            }

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
