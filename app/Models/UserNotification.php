<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $fillable = [
        'owner_user_id','type','title','body','data',
        'contact_id','reminder_id','status','scheduled_at','read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'scheduled_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    /* Helpers */
    public static function log(int $userId, array $payload): self
    {
        $row = static::create(array_merge($payload, ['owner_user_id' => $userId]));
        static::pruneForUser($userId);
        return $row;
    }

    /** Keep maximum 50 records per user */
    public static function pruneForUser(int $userId): void
    {
        $idsToKeep = static::where('owner_user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->pluck('id');

        if ($idsToKeep->count() < 50) return;

        static::where('owner_user_id', $userId)
            ->whereNotIn('id', $idsToKeep)
            ->delete();
    }
}
