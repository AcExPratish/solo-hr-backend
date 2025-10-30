<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Leave extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'leave_type_id',
        'from_date',
        'to_date',
        'total_days',
        'reason',
        'status',
        'approved_by_id',
        'created_by_id',
        'updated_by_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}
