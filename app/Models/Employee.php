<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Employee extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'employees';

    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'basic_information',
        'emergency_contact',
        'bank_information',
        'family_information',
        'statutory_information',
        'supporting_documents',
        'education',
        'experience',
        'deleted_at',
        'created_by_id',
        'updated_by_id'
    ];

    protected $casts = [
        'basic_information'     => 'array',
        'emergency_contact'     => 'array',
        'bank_information'      => 'array',
        'family_information'    => 'array',
        'statutory_information' => 'array',
        'supporting_documents'  => 'array',
        'education'             => 'array',
        'experience'            => 'array',
        'deleted_at'            => 'datetime'
    ];

    public function toArray()
    {
        $attributes = parent::toArray();
        if (isset($attributes['id'])) {
            $attributes['_id'] = (string) $this->id;
        }

        unset($attributes['id']);

        return $attributes;
    }
}
