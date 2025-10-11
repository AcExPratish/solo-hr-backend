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
}
