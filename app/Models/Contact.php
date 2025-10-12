<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'owner_user_id',
        'name', 'company', 'email', 'phone',
        'address', 'notes',
        'job_title', 'linkedin_url', 'website_url',
        'ocr_raw', 'duplicate_of_id', 'search_text', 'source',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** Chủ sở hữu của contact */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** Tags của contact */
    public function tags(): BelongsToMany
    {
        // pivot mặc định là 'contact_tag' (đúng schema hiện tại)
        return $this->belongsToMany(Tag::class)->withTimestamps();
    }

    /** Các reminder của contact (mặc định sắp xếp theo due_at tăng dần) */
  public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class)->orderBy('due_at', 'asc');
    }

    /** Quan hệ mới: many-to-many qua pivot contact_reminder (bao gồm cả contact chính) */
    public function remindersMany(): BelongsToMany
    {
        return $this->belongsToMany(Reminder::class, 'contact_reminder')
            ->withTimestamps()
            ->withPivot([]); // thêm cột pivot nếu sau này có
    }
}
