<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="BusinessCard",
 *     type="object",
 *     title="Business Card",
 *     required={"full_name", "email"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="company_id", type="integer", example=1),
 *     @OA\Property(property="full_name", type="string", example="John Doe"),
 *     @OA\Property(property="job_title", type="string", example="Software Engineer"),
 *     @OA\Property(property="department", type="string"),
 *     @OA\Property(property="email", type="string"),
 *     @OA\Property(property="phone", type="string"),
 *     @OA\Property(property="mobile", type="string"),
 *     @OA\Property(property="website", type="string"),
 *     @OA\Property(property="address", type="string"),
 *     @OA\Property(property="linkedin", type="string"),
 *     @OA\Property(property="facebook", type="string"),
 *     @OA\Property(property="twitter", type="string"),
 *     @OA\Property(property="avatar", type="string"),
 *     @OA\Property(property="notes", type="string"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class BusinessCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'full_name',
        'job_title',
        'department',
        'email',
        'phone',
        'mobile',
        'website',
        'address',
        'linkedin',
        'facebook',
        'twitter',
        'avatar',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
