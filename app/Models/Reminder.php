<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use DateTimeInterface;
use DateTimeZone;

class Reminder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contact_id',       // "primary" contact
        'owner_user_id',
        'title',
        'note',
        'due_at',
        'status',
        'channel',
        'external_event_id',
    ];

    protected $casts = [
        'due_at'     => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * REQUIRED: Normalize datetime format returned to API as ISO 8601 UTC (with 'Z').
     * This ensures FE (using new Date(iso)) displays correct local-time consistently across pages.
     */

    /** "Primary" contact */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** All related contacts (including primary contact) via contact_reminder pivot */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_reminder')->withTimestamps();
    }
}
