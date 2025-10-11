<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RolePermission extends Pivot
{
    protected $table = 'role_permission';

    protected $primaryKey = ['role_id', 'permission_id'];

    public $incrementing = false;

    protected $fillable = [
        'role_id',
        'permission_id'
    ];
}
