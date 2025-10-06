<?php
// app/Models/Tag.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = ['owner_user_id','name'];

    public function contacts()
    {
        return $this->belongsToMany(Contact::class)->withTimestamps();
    }
}
