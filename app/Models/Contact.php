<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
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
        'avatar',
        'card_image_front',
        'card_image_back',
    ];

    protected $appends = ['avatar_url', 'card_front_url', 'card_back_url'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    public function getCardFrontUrlAttribute(): ?string
    {
        return $this->card_image_front ? Storage::disk('public')->url($this->card_image_front) : null;
    }

    public function getCardBackUrlAttribute(): ?string
    {
        return $this->card_image_back ? Storage::disk('public')->url($this->card_image_back) : null;
    }

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

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
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
