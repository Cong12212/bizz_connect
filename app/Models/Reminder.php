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
        'contact_id',       // contact “chính”
        'owner_user_id',
        'title', 'note', 'due_at',
        'status', 'channel', 'external_event_id',
    ];

    protected $casts = [
        'due_at'     => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * BẮT BUỘC: Chuẩn hoá format datetime trả về API thành ISO 8601 UTC (có 'Z').
     * Nhờ vậy FE (dùng new Date(iso)) sẽ hiển thị đúng local-time và đồng nhất giữa các trang.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM); // ví dụ: 2025-10-13T07:44:00+00:00
        // hoặc dùng 'c' cũng được: ->format('c')
    }

    /** Contact “chính” (primary) */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Tất cả contacts liên quan (kể cả contact chính) qua pivot contact_reminder */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_reminder')->withTimestamps();
    }
}
