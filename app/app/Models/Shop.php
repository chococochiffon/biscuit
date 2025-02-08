<?php

namespace App\Models;

use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'title',
        'detail',
        'logo',
        'favicon',
        'address1',
        'address2',
        'address3',
        'nearest',
        'is_sunday_start',
        'business_hour_text',
        'business_day_text',
        'last_order_text',
        'business_hour',
        'business_days',
        'business_hour',
        'last_order',
        'notch',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $casts = [];
}
