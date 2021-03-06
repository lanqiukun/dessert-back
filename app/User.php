<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;


class User extends Authenticatable
{

    protected $guarded = [];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
