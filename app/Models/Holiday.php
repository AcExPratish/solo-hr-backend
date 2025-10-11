<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasUuids;

    protected $fillable = [
        'title',
        'date',
        'description',
        'status',
        'created_by_id',
        'updated_by_id',
    ];
}
