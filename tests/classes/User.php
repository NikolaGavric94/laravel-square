<?php

namespace Nikolag\Square\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Nikolag\Square\Traits\HasCustomers;

class User extends Authenticatable
{
    use HasCustomers;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email', 'name',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
}
