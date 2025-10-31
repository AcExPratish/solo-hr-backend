<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    use HasUuids;

    protected $fillable = [
        'leave_type_id',
        'user_id',
        'policy_name',
        'total_days',
        'remaining_days',
        'created_by_id',
        'updated_by_id'
    ];

    public function type()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
