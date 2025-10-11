<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'in_note',
        'out_note',
    ];

    public function scopeFilterByDate($query)
    {
        if (request()->has('date')) {
            return $query->whereDate('date', request('date'));
        }

        return $query;
    }

    public function scopeFilterByUserId($query)
    {
        if (request()->has('user_id')) {
            return $query->where('user_id', request('user_id'));
        }

        return $query;
    }
}
