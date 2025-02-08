<?php

namespace App\Models;

use Database\Factories\StaffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Staff extends Authenticatable
{
    /** @use HasFactory<StaffFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'shop_id',
        'name',
        'first_name',
        'last_name',
        'nickname',
        'x_id',
        'email',
        'race',
        'sex',
        'self_introduction',
        'x_url',
        'password',
        'order',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function role(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'staff_roles');
    }

    public function reservation(): BelongsToMany
    {
        return $this->belongsToMany(Reservation::class, 'staff_reservations');
    }

    public function shift(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function staffImage(): HasMany
    {
        return $this->hasMany(StaffImage::class);
    }
}
