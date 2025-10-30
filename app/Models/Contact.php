<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_user_id',
        'name',
        'company',
        'job_title',
        'email',
        'phone',
        'address_id',
        'notes',
        'linkedin_url',
        'website_url',
        'ocr_raw',
        'duplicate_of_id',
        'search_text',
        'source',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** Owner of the contact */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** Tags of the contact (pivot: contact_tag) */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /** (Optional, legacy) one-to-many reminders if you still use contact_id field on reminders */
    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class)->orderBy('due_at', 'asc');
    }

    /** Many-to-many via pivot (unified table name: contact_reminder) */
    public function remindersMany(): BelongsToMany
    {
        return $this->belongsToMany(
            Reminder::class,
            'contact_reminder',   // 👈 FIXED pivot name
            'contact_id',
            'reminder_id'
        )
            ->withPivot(['is_primary'])
            ->withTimestamps();
    }

    public function addresses()
    {
        return $this->belongsToMany(Address::class, 'contact_addresses')
            ->withPivot('address_type_code', 'date_from', 'date_to')
            ->withTimestamps();
    }

    public function getAddressByType($type = 'home')
    {
        return $this->addresses()
            ->wherePivot('address_type_code', $type)
            ->first();
    }

    /* ---------------------- Reusable Scopes ---------------------- */

    /** Limit by owner */
    public function scopeOwnedBy(Builder $q, int $userId): Builder
    {
        return $q->where('owner_user_id', $userId);
    }

    /**
     * Filter contacts WITHOUT reminders matching conditions
     * options:
     *  - status: pending|done|... (optional)
     *  - after, before: ISO datetime or any parsable by Carbon (optional)
     */
    public function scopeWithReminder(Builder $q, array $opt = []): Builder
    {
        $status = $opt['status'] ?? null;
        $after  = !empty($opt['after'])  ? Carbon::parse($opt['after'])  : null;
        $before = !empty($opt['before']) ? Carbon::parse($opt['before']) : null;

        return $q->whereExists(function ($sub) use ($status, $after, $before) {
            $sub->selectRaw(1)
                ->from('contact_reminder as cr')
                ->join('reminders as r', 'r.id', '=', 'cr.reminder_id')
                ->whereColumn('cr.contact_id', 'contacts.id')
                ->whereColumn('r.owner_user_id', 'contacts.owner_user_id')
                ->when($status, fn($w) => $w->where('r.status', $status))
                ->when($after,  fn($w) => $w->where('r.due_at', '>=', $after))
                ->when($before, fn($w) => $w->where('r.due_at', '<=', $before));
        });
    }
}
