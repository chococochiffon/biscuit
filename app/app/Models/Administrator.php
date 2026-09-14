<?php

namespace App\Models;

use App\Enums\AdministratorRole;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'email', 'password', 'role', 'last_login_at'])]
#[Hidden(['password'])]
class Administrator extends Model
{
    /** @use HasFactory<AdministratorFactory> */
    use HasFactory;

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
