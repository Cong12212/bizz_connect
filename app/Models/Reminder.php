<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reminder extends Model {
  use SoftDeletes;
  protected $guarded = [];
  public function contact(){ return $this->belongsTo(Contact::class); }
  public function owner(){ return $this->belongsTo(User::class,'owner_user_id'); }
}
