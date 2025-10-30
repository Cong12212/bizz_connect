<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = ['address_detail', 'city_id', 'state_id', 'country_id'];

    protected $appends = ['full_address'];

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function getFullAddressAttribute()
    {
        $parts = array_filter([
            $this->address_detail,
            $this->city?->name,
            $this->state?->name,
            $this->country?->name
        ]);
        return implode(', ', $parts);
    }
}
