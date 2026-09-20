<?php

namespace App\Models;

use App\Enums\AdministratorRole;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'email', 'password', 'role', 'last_login_at'])]
#[Hidden(['password', 'unique_email'])]
class Administrator extends Authenticatable
{
    /** @use HasFactory<AdministratorFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => AdministratorRole::class,
            'last_login_at' => 'datetime',
        ];
    }
}
