<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'is_paid',
        'description',
        'created_by_id',
        'updated_by_id'
    ];

    public function policies()
    {
        return $this->hasMany(LeavePolicy::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }
}
