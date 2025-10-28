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
        return $this->belongsToMany(Company::class)
            ->withPivot(['role', 'deleted_at'])
            ->withTimestamps();
    }

    // 1-N: personal subscriptions (user_id
}
