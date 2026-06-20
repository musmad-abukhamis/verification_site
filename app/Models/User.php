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

    public function kyc(): HasMany
    {
        return $this->hasMany(AccountKyc::class, 'userId');
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
