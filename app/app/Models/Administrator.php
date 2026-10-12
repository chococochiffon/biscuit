<?php

namespace App\Models;

use App\Enums\AdministratorRole;
use App\Enums\TwoFactorMethod;
use App\Models\Concerns\HasTwoFactor;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'last_login_at'])]
#[Hidden(['password', 'unique_email', 'two_factor_secret', 'two_factor_last_used_step'])]
class Administrator extends Authenticatable
{
    /** @use HasFactory<AdministratorFactory> */
    use HasFactory, HasTwoFactor, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_method' => TwoFactorMethod::class,
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_step' => 'integer',
            'role' => AdministratorRole::class,
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * スーパー管理者かどうか(カスタムページ管理など、スーパー管理者だけが使える機能の判定に使う)。
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === AdministratorRole::SuperAdmin;
    }
}
