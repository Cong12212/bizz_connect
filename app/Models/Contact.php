<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model {
    use SoftDeletes;

    protected $fillable = [
        'owner_user_id',
        'name','company','email','phone',
        'address','notes',
        'job_title','linkedin_url','website_url',
        'ocr_raw','duplicate_of_id','search_text','source',
    ];

    public function owner(){ return $this->belongsTo(User::class,'owner_user_id'); }
    public function tags(){ return $this->belongsToMany(Tag::class)->withTimestamps(); }
    public function reminders(){ return $this->hasMany(Reminder::class); }
}