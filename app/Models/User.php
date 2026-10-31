<?php

namespace App\Models;

use App\Domains\Shared\Enums\UserStatus;
use App\Domains\Shared\Services\LocalTestingAccess;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property UserStatus $status
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $gender
 * @property string|null $profile_photo_path
 * @property string|null $avatar
 * @property Carbon|null $email_verified_at
 * @property string|null $whatsapp_number
 * @property Carbon|null $whatsapp_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Appends(['avatar'])]
#[Fillable(['name', 'email', 'password', 'whatsapp_number', 'gender'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $attributes = ['status' => 'active'];

    /** @return Attribute<string|null, never> */
    protected function avatar(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->profile_photo_path === null ? null : Storage::disk('public')->url($this->profile_photo_path));
    }

    public function hasVerifiedEmail(): bool
    {
        return parent::hasVerifiedEmail() || app(LocalTestingAccess::class)->allows($this);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'whatsapp_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
