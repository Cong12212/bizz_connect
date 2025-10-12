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
     * Nếu DB của bạn có thêm các cột như phone, avatar_url, locale, timezone
     * thì nên thêm vào fillable cho tiện mass assignment.
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
     * Expose 2 computed fields khi serialize sang JSON.
     */
    protected $appends = ['is_plus', 'effective_plan'];

    /* ===================== Relationships ===================== */

    // N-n qua bảng pivot company_user (đã có role + softDeletes)
    public function companies()
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['role', 'deleted_at'])
            ->withTimestamps();
    }

    // 1-n: các subscription cấp cá nhân (user_id)
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /* ===================== Accessors ===================== */

    // User có Plus? (có sub cá nhân active hoặc thuộc công ty có sub active)
    public function getIsPlusAttribute(): bool
    {
        // 1) Plus cá nhân
        $hasPersonal = $this->subscriptions()
            ->active()
            ->whereIn('plan', ['pro', 'pro_plus'])
            ->exists();
        if ($hasPersonal) return true;

        // 2) Plus công ty (bất kỳ công ty nào user thuộc về)
        $companyIds = $this->companies()->pluck('companies.id');
        if ($companyIds->isEmpty()) return false;

        return Subscription::active()
            ->whereIn('company_id', $companyIds)
            ->whereNull('user_id')
            ->whereIn('plan', ['pro', 'pro_plus'])
            ->exists();
    }

    // Kế thừa plan hiệu lực: ưu tiên cá nhân > công ty > free
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
