<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *   schema="Company",
 *   type="object",
 *   title="Company",
 *   required={"name"},
 *   @OA\Property(property="id", type="integer", example=1),
 *   @OA\Property(property="name", type="string", example="Tech Corp"),
 *   @OA\Property(property="tax_code", type="string", nullable=true),
 *   @OA\Property(property="phone", type="string", nullable=true),
 *   @OA\Property(property="email", type="string", nullable=true),
 *   @OA\Property(property="website", type="string", nullable=true),
 *   @OA\Property(property="description", type="string", nullable=true),
 *   @OA\Property(property="logo", type="string", nullable=true),
 *   @OA\Property(property="address_id", type="integer", nullable=true)
 * )
 */
class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'tax_code',
        'phone',
        'email',
        'website',
        'address_id',
        'description',
        'logo',
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    // Nếu users.company_id trỏ về companies.id
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
