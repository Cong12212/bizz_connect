<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id','user_id','plan','status',
        'current_period_start','current_period_end',
        'payment_provider','provider_customer_id','provider_subscription_id',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end'   => 'datetime',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function user()    { return $this->belongsTo(User::class); }

    public function scopeActive($q) {
        return $q->where('status','active')
                 ->where(function($qq){
                    $qq->whereNull('current_period_end')
                       ->orWhere('current_period_end','>', now());
                 });
    }

    public function scopeForUser($q, int $userId) { return $q->where('user_id', $userId); }
    public function scopeForCompany($q, int $companyId) { return $q->where('company_id', $companyId); }

    public function isActiveAt(?CarbonInterface $when = null): bool
    {
        $when = $when ?: now();
        return $this->status === 'active' && (is_null($this->current_period_end) || $this->current_period_end > $when);
    }

    public function isCompanyLevel(): bool { return !is_null($this->company_id); }
    public function isUserLevel(): bool    { return !is_null($this->user_id); }
}
