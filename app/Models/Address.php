<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    protected $fillable = ['line1', 'line2', 'city', 'state', 'country'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function businessCards()
    {
        return $this->hasMany(BusinessCard::class);
    }

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    public function getFullAddressAttribute()
    {
        $parts = array_filter([$this->line1, $this->line2]);
        return implode(', ', $parts);
    }

    public function getCityNameAttribute()
    {
        $city = City::where('code', $this->city)->first();
        return $city ? $city->name : $this->city;
    }

    public function getStateNameAttribute()
    {
        $state = State::where('code', $this->state)->first();
        return $state ? $state->name : $this->state;
    }

    public function getCountryNameAttribute()
    {
        $country = Country::where('code', $this->country)->first();
        return $country ? $country->name : $this->country;
    }
}
