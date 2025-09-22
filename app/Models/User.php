<?php

use Laravel\Sanctum\HasApiTokens;
class User extends Authenticatable {
    use HasApiTokens, Notifiable;
    protected $guarded = [];
}
