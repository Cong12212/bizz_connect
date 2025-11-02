<?php

namespace App\Models;

use App\Enums\Plan;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// Sanctum
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * If your DB has additional columns like phone, avatar_url, locale, timezone
     * then add them to fillable for convenient mass assignment.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar_url',
        'locale',
        'timezone',
        'company_id',
        'business_card_id',
        'address_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Expose 2 computed fields when serializing to JSON.
     */

    /* ===================== Relationships ===================== */

    // N-N via company_user pivot table (includes role + softDeletes)
    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public function businessCards()
    {
        return $this->hasMany(BusinessCard::class);
    }

    // 1-N: personal subscriptions (user_id
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function businessCard()
    {
        return $this->hasOne(BusinessCard::class, 'user_id');
    }

    public function addresses()
    {
        return $this->belongsToMany(Address::class, 'user_addresses')->withTimestamps();
    }

    public function getAddressByType($type = 'home')
    {
        return $this->addresses()
            ->wherePivot('address_type_code', $type)
            ->first();
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
