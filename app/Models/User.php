<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\HasPrismaId;
use App\Models\Concerns\ManagesWallet;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Prisma model: User (table "users").
 *
 * @method bool isAdmin()
 */
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPrismaId, ManagesWallet, MustVerifyEmailTrait, Notifiable;

    protected $table = 'users';

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'balance',
        'username',
        'password',
        'role',
        'image',
        'apitoken',
        'isTwoFactorEnabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified' => 'datetime',
            'password' => 'hashed',
            'balance' => 'float',
            'isTwoFactorEnabled' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN;
    }

    /**
     * Resolve an account from whatever identifier someone typed: email,
     * username or phone. Used by both login and password reset, which must
     * agree -- an identifier that signs you in has to be one you can reset with.
     *
     * Username matters because the ~2250 accounts migrated from nimcweb signed
     * in with their username there and mostly do not recall which email address
     * they registered with.
     *
     * Phone is matched across the 0xxx / 234xxx / +234xxx spellings of the same
     * number, but only when exactly one account matches: 15 migrated accounts
     * collide once normalised, and picking the first would hand someone another
     * person's account. Those users can still use their username or email.
     */
    public static function findByIdentifier(string $identifier): ?self
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return static::whereRaw('lower(email) = ?', [Str::lower($identifier)])->first();
        }

        $user = static::whereRaw('lower(username) = ?', [Str::lower($identifier)])->first()
            ?? static::where('phone', $identifier)->first();

        if ($user) {
            return $user;
        }

        $digits = preg_replace('/\D/', '', $identifier);

        if (strlen($digits) < 10) {
            return null;
        }

        // Build the equivalent spellings in PHP rather than normalising the
        // column in SQL: it keeps the phone index usable, and avoids
        // regexp_replace/right(), which are Postgres-only and would break the
        // SQLite-backed test suite.
        $local = substr($digits, -10);

        $matches = static::whereIn('phone', [
            '0'.$local, '234'.$local, '+234'.$local, $local,
        ])->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * The Prisma schema stores the verification timestamp in `email_verified`
     * (not Laravel's default `email_verified_at`), so override the two
     * MustVerifyEmail methods that touch the column.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified);
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified' => $this->freshTimestamp(),
        ])->save();
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class, 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'userId');
    }

    public function ninDetails(): HasMany
    {
        return $this->hasMany(NinDetail::class, 'userId');
    }

    public function walletHistory(): HasMany
    {
        return $this->hasMany(WalletHistory::class, 'userId');
    }

    public function dataTransactions(): HasMany
    {
        return $this->hasMany(DataTransaction::class, 'user_id');
    }

    public function walletEntries(): HasMany
    {
        return $this->hasMany(WalletEntry::class, 'user_id');
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class, 'user_id');
    }

    public function kyc(): HasMany
    {
        return $this->hasMany(AccountKyc::class, 'userId');
    }

    /**
     * The user's reserved virtual accounts (Billstack), formatted for display.
     *
     * @return array<int, array{bank: string, account_number: string, account_name: string}>
     */
    public function reservedAccounts(): array
    {
        return optional($this->kyc()->first())->toFormattedAccounts() ?? [];
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'userId');
    }

    public function notificationUsers(): HasMany
    {
        return $this->hasMany(NotificationUser::class, 'userId');
    }

    public function ipe(): HasMany
    {
        return $this->hasMany(Ipe::class, 'userId');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(Validation::class, 'userId');
    }

    public function personalisations(): HasMany
    {
        return $this->hasMany(Personalisation::class, 'userId');
    }

    public function bvnModifications(): HasMany
    {
        return $this->hasMany(BvnModification::class, 'userId');
    }

    public function bvnSdkForms(): HasMany
    {
        return $this->hasMany(BvnSdkForm::class, 'userId');
    }

    public function bvnRetrievals(): HasMany
    {
        return $this->hasMany(BvnRetrieval::class, 'userId');
    }

    public function idCardRequests(): HasMany
    {
        return $this->hasMany(IdCard::class, 'userId');
    }

    public function otp(): HasOne
    {
        return $this->hasOne(Otp::class, 'userId');
    }

    public function pin(): HasOne
    {
        return $this->hasOne(Pin::class, 'userId');
    }

    public function twoFactorConfirmation(): HasOne
    {
        return $this->hasOne(TwoFactorConfirmation::class, 'userId');
    }
}
