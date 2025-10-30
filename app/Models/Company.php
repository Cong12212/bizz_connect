<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="Company",
 *     type="object",
 *     title="Company",
 *     required={"name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Tech Corp"),
 *     @OA\Property(property="domain", type="string", example="techcorp.com"),
 *     @OA\Property(property="industry", type="string", example="Technology"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="website", type="string", example="https://techcorp.com"),
 *     @OA\Property(property="email", type="string", example="info@techcorp.com"),
 *     @OA\Property(property="phone", type="string", example="+1234567890"),
 *     @OA\Property(property="address", type="string"),
 *     @OA\Property(property="city", type="string", example="San Francisco"),
 *     @OA\Property(property="country", type="string", example="USA"),
 *     @OA\Property(property="logo", type="string"),
 *     @OA\Property(property="plan", type="string", example="free"),
 *     @OA\Property(property="status", type="string", example="active"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'domain',
        'industry',
        'description',
        'website',
        'email',
        'phone',
        'address_id',
        'logo',
        'plan',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function businessCard()
    {
        return $this->hasOne(BusinessCard::class);
    }

    public function addresses()
    {
        return $this->belongsToMany(Address::class, 'company_addresses')->withTimestamps();
    }

    public function getAddressByType($type = 'business')
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
