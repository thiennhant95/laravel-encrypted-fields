<?php

namespace Thiennhant95\LaravelEncryptedFields\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Thiennhant95\LaravelEncryptedFields\Concerns\HasEncryptedFields;

final class User extends Authenticatable
{
    use HasEncryptedFields;

    protected $table = 'users';

    protected $guarded = array();

    protected $hidden = array('password', 'remember_token');
}
