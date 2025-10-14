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
        'name', 'email', 'password',
        'phone', 'avatar_url', 'locale', 'timezone',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Expose 2 computed fields when serializing to JSON.
     */
    protected $appends = ['is_plus', 'effective_plan'];

    /* ===================== Relationships ===================== */

    // N-N via company_user pivot table (includes role + softDeletes)
    public function companies()
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['role', 'deleted_at'])
            ->withTimestamps();
    }

    // 1-N: personal subscriptions (user_id)
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /* ===================== Accessors ===================== */

    // Does user have Plus? (has active personal sub or belongs to company with active sub)
    public function getIsPlusAttribute(): bool
    {
        // 1) Personal Plus
        $hasPersonal = $this->subscriptions()
            ->active()
            ->whereIn('plan', ['pro', 'pro_plus'])
            ->exists();
        if ($hasPersonal) return true;

        // 2) Company Plus (any company user belongs to)
        $companyIds = $this->companies()->pluck('companies.id');
        if ($companyIds->isEmpty()) return false;

        return Subscription::active()
            ->whereIn('company_id', $companyIds)
            ->whereNull('user_id')
            ->whereIn('plan', ['pro', 'pro_plus'])
            ->exists();
    }

    // Effective plan inheritance: personal > company > free
    public function getEffectivePlanAttribute(): string
    {
        $personal = $this->subscriptions()
            ->active()
            ->orderByDesc('current_period_end')
            ->first();
        if ($personal) return $personal->plan;

        $companyIds = $this->companies()->pluck('companies.id');
        if ($companyIds->isNotEmpty()) {
            $companySub = Subscription::active()
                ->whereIn('company_id', $companyIds)
                ->whereNull('user_id')
                ->orderByDesc('current_period_end')
                ->first();
            if ($companySub) return $companySub->plan;
        }

        return Plan::Free->value; // 'free'
    }
}
