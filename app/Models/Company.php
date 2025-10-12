<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name','domain','status'];

    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role','deleted_at'])
            ->withTimestamps();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function hasCompanyPlus(): bool
    {
        return $this->subscriptions()
            ->active()
            ->whereIn('plan', ['pro','pro_plus'])
            ->exists();
    }
}
