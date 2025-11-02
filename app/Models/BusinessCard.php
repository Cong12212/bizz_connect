<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
 *     @OA\Property(property="avatar", type="string"),
 *     @OA\Property(property="notes", type="string"),
 *     @OA\Property(property="slug", type="string", example="john-doe-tech-corp"),
 *     @OA\Property(property="is_public", type="boolean", example=true),
 *     @OA\Property(property="view_count", type="integer", example=42),
 *     @OA\Property(
 *        property="address",
 *        type="object",
 *        nullable=true,
 *        @OA\Property(property="id", type="integer"),
 *        @OA\Property(property="address_detail", type="string"),
 *        @OA\Property(property="city_id", type="integer", nullable=true),
 *        @OA\Property(property="state_id", type="integer", nullable=true),
 *        @OA\Property(property="country_id", type="integer", nullable=true),
 *     )
 * )
 */
class BusinessCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'slug',
        'full_name',
        'job_title',
        'department',
        'email',
        'phone',
        'mobile',
        'website',
        'address_id',
        'linkedin',
        'facebook',
        'twitter',
        'avatar',
        'notes',
        'is_public',
        'view_count',
    ];

    protected $casts = [
        'is_public'  => 'boolean',
        'view_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($card) {
            if (empty($card->slug)) {
                $card->slug = static::generateUniqueSlug($card->full_name);
            }
        });

        static::updating(function ($card) {
            if ($card->isDirty('full_name') && empty($card->slug)) {
                $card->slug = static::generateUniqueSlug($card->full_name);
            }
        });
    }

    protected static function generateUniqueSlug($name)
    {
        $base = Str::slug($name) ?: 'card';
        $slug = $base;
        $count = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $count++;
        }

        return $slug;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function address()
    {
        return $this->belongsTo(Address::class);
    }

    public function getPublicUrlAttribute()
    {
        return rtrim(config('app.frontend_url'), '/') . '/card/' . $this->slug;
    }
}
